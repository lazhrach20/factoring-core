# Архитектура системы управления компаниями и счетами

## Обзор системы

Реализована подсистема административного управления компаниями-клиентами и их банковскими счетами в рамках факторинговой платформы. Система построена на принципах Domain-Driven Design с четким разделением bounded contexts и использует архитектуру REST API + SPA.

## Bounded Contexts и структура домена

### 1. Identity Context (`src/Identity/`)

Отвечает за управление идентификацией компаний в системе. Ключевое архитектурное решение - выделение Identity как отдельного контекста от Banking, что обеспечивает слабую связанность и позволяет независимо масштабировать функциональность.

**Сущности:**
- `Company` - агрегат, представляющий юридическое лицо
  - Использует Uuid v4 в качестве идентификатора (предотвращает enumeration attacks)
  - Инкапсулирует бизнес-правила активации/деактивации
  - Содержит value objects: CompanyType (enum для типологии)
  - Валидация ИНН на уровне сущности

**Инфраструктура:**
- `CompanyRepository` - использует Doctrine ORM с identity map pattern
- `CompanyController` - RESTful API с явными action методами вместо generic CRUD

**Принятые решения:**

1. **Soft delete вместо физического удаления** - компании деактивируются через поле `isActive`, а не удаляются. Причина: сохранение истории операций и связей с финансовыми транзакциями.

2. **Отдельный метод активации** - `POST /api/companies/{id}/activate` вместо PATCH с флагом. Причина: активация - это бизнес-операция с потенциальными side-effects (отправка уведомлений, активация связанных сервисов), а не простое изменение поля.

3. **Использование DTO вместо прямой десериализации в entity** - все входящие данные сначала валидируются через Symfony Validator. Причина: предотвращение mass assignment уязвимостей и явный контроль над тем, какие поля могут быть изменены.

### 2. Banking Context (`src/Banking/`)

Управление банковскими счетами клиентов. Критически важный bounded context с жесткими требованиями к консистентности данных.

**Сущности:**
- `Account` - агрегат банковского счета
  - Связан с Company через composition (не может существовать без компании)
  - Связан с User (владелец счета) - должен принадлежать той же компании
  - Валидация БИК, номера счета, корр. счета на уровне сущности

**Инфраструктура:**
- `AccountRepository` - обеспечивает загрузку с eager loading связей для предотвращения N+1 проблемы
- `AccountController` - полный CRUD с бизнес-валидацией

**Принятые решения:**

1. **Валидация принадлежности owner к company** - на уровне контроллера проверяется, что владелец счета работает в той же компании. Причина: предотвращение инсайдерских атак и случайных ошибок операторов.

2. **Cascade operations для связанных сущностей** - при деактивации компании все ее счета остаются, но помечаются как неактивные. Причина: соответствие законодательству о хранении банковских данных.

3. **Отдельный контроллер для счетов вместо nested resource** - `/api/accounts` вместо `/api/companies/{id}/accounts`. Причина: счета - это полноценные агрегаты, которые могут запрашиваться независимо от компании, плюс упрощение роутинга.

### 3. Shared Context (`src/Shared/`)

Общие компоненты, используемые несколькими bounded contexts. Следование принципу DRY без создания God Object.

## Слой безопасности

### Аутентификация

**JWT-based authentication** с refresh token pattern:
- Access token хранится в localStorage (срок жизни 1 час)
- Refresh token в httpOnly cookie (защита от XSS)
- Middleware автоматически обновляет токены при истечении

**Архитектурное решение:** Использование симметричного алгоритма HS256 вместо асимметричного RS256. Обоснование: монолитная архитектура не требует распределенной верификации токенов, HS256 быстрее на 10-15% и проще в ротации ключей.

### Авторизация

**Role-Based Access Control (RBAC)** с гранулярными ролями:
- `ROLE_ADMIN` - полный доступ к управлению компаниями и счетами
- `ROLE_MANAGER` - ограниченный доступ (чтение + создание, но не удаление)
- `ROLE_USER` - доступ только к своей компании
- `ROLE_AUDIT_VIEWER` - read-only доступ для аудита

**Принятые решения:**

1. **Проверка ролей на уровне контроллера** через `#[IsGranted('ROLE_ADMIN')]` вместо voter pattern. Причина: требования к авторизации статичные и не зависят от контекста данных, атрибуты проще читать и тестировать.

2. **Множественные роли у пользователя** - User может иметь несколько ролей одновременно. Причина: реальные сценарии, когда админ также является менеджером определенного департамента.

3. **Отдельный endpoint для загрузки пользователей компании** - `/api/admin/users?companyId=X` с явной проверкой роли. Причина: предотвращение IDOR (Insecure Direct Object Reference) атак.

### Защита от злоумышленников

**1. SQL Injection Protection:**
- Использование Doctrine ORM с prepared statements
- Все query parameters параметризованы
- DQL используется вместо raw SQL

**2. XSS Protection:**
- Все динамические данные экранируются через `escapeHtml()` на frontend
- Content-Security-Policy заголовки (можно добавить в nginx)
- Использование data-атрибутов вместо inline event handlers

**3. CSRF Protection:**
- JWT в Authorization заголовке, не в cookies (нет автоматической отправки)
- SameSite cookie атрибут для refresh tokens
- Stimulus controller для автоматического добавления токенов к запросам

**4. Rate Limiting (рекомендация для продакшена):**
```php
// Можно добавить через Symfony RateLimiter
#[RateLimit(limit: 100, period: 60)]
```

**5. Input Validation:**
- Двухуровневая валидация: на frontend (UX) и backend (security)
- Symfony Validator с кастомными constraints для ИНН, БИК
- Type hints в PHP для type safety

### Защита от ошибок пользователей

**1. UI/UX защита:**
- **Confirmation dialogs** для деструктивных операций (деактивация, удаление)
  - Promise-based модалки вместо window.confirm() для более надежного UX
  - Требование явного подтверждения с читаемым текстом операции

- **Toast notifications** вместо alert() для информирования:
  - Цветовая индикация (красный/зеленый/желтый/синий)
  - Автоматическое закрытие через 4 секунды (не блокирует UI)
  - Стек уведомлений для одновременных событий

- **Disable buttons during operations** - предотвращение двойных submit'ов

**2. Backend защита:**
- **Idempotency keys** для критичных операций (можно добавить):
  ```php
  #[Route('/api/accounts', methods: ['POST'])]
  public function create(Request $request, #[RequestAttribute] ?string $idempotencyKey = null)
  ```

- **Optimistic locking** через Doctrine version field - предотвращение lost updates при конкурентных изменениях

- **Transactional consistency:**
  ```php
  $this->entityManager->wrapInTransaction(function() {
      // критичные операции
  });
  ```

**3. Data validation:**
- **ИНН валидация** по контрольной сумме (российский стандарт)
- **БИК валидация** по справочнику ЦБ РФ
- **Email валидация** через egulias/email-validator (RFC 5322 compliant)
- **Phone number normalization** перед сохранением

## Frontend Architecture

### Выбор технологического стека

**Vanilla JS + Tabler UI** вместо React/Vue:

**Обоснование:**
1. **Простота деплоя** - один HTML файл, нет build процесса
2. **Performance** - нет overhead от virtual DOM (критично для админки с большими таблицами)
3. **Низкий порог входа** - любой backend разработчик может быстро внести изменения
4. **SEO не критично** - внутренняя админка

**Trade-off:** Отсутствие реактивности компенсируется явными update функциями (`renderCompanies()`, `updateAccountList()`).

### Архитектурные паттерны

**1. Module Pattern:**
```javascript
// Все функции инкапсулированы в IIFE
(function() {
    // приватные переменные
    const API_BASE_URL = 'http://localhost:8080/api';

    // публичные функции через event listeners
    window.addEventListener('load', init);
})();
```

**2. Promise-based Async Operations:**
```javascript
async function showConfirm(message, title) {
    return new Promise((resolve) => {
        // модалка с resolve(true/false)
    });
}
```
Причина: позволяет писать линейный код вместо callback hell.

**3. Separation of Concerns:**
- Presentation logic (renderCompanies) отделена от business logic (createCompany)
- API layer (fetch calls) изолирован в отдельных функциях
- Utility functions (escapeHtml, formatRoleName) переиспользуемы

**4. Progressive Enhancement:**
- Базовая функциональность работает без JS (формы отправляются)
- JS добавляет UX улучшения (toast, модалки)

### State Management

**Stateless approach** - состояние не хранится в памяти:
- При каждом действии перезапрашиваются данные с сервера
- Нет синхронизации client-server state

**Обоснование:**
- Простота реализации
- Актуальность данных (важно для финансовой системы)
- Избежание race conditions при concurrent updates

**Trade-off:** Больше сетевых запросов, но с учетом небольшого объема данных (<100 компаний) - приемлемо.

### Security на Frontend

**1. Token Management:**
```javascript
const token = localStorage.getItem('accessToken');
if (!token) {
    // редирект с сохранением intended URL
    window.location.href = `login.html?redirect_after_login=${encodeURIComponent(window.location.href)}`;
}
```

**2. XSS Prevention:**
```javascript
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;  // браузер автоматически экранирует
    return div.innerHTML;
}
```

**3. Safe Event Handlers:**
```javascript
// Вместо: onclick="deleteAccount('${account.id}')"
// Используем:
card.querySelector('[data-action="delete"]').addEventListener('click', () => {
    deleteAccount(account.id);
});
```

## API Design Principles

### RESTful conventions

**Resource-based URLs:**
```
GET    /api/companies           - список
GET    /api/companies/{id}      - детали
POST   /api/companies           - создание
PATCH  /api/companies/{id}      - обновление
POST   /api/companies/{id}/activate   - бизнес-операция
POST   /api/companies/{id}/deactivate - бизнес-операция
```

**Принятые решения:**

1. **PATCH вместо PUT** для обновления - можно менять отдельные поля без отправки всего объекта.

2. **POST для бизнес-операций** (activate/deactivate) вместо PATCH - явно показывает, что это action, а не просто изменение данных.

3. **Plural nouns** (/companies, не /company) - стандарт REST.

### Response Format

**Единый формат ответов:**
```json
{
    "companies": [...],
    "total": 42
}
```

Для ошибок:
```json
{
    "error": "Validation failed",
    "details": {
        "inn": "Invalid INN format"
    }
}
```

**Обоснование:** Консистентность упрощает обработку на frontend и позволяет создать generic error handler.

### HTTP Status Codes

Правильное использование семантики HTTP:
- `200 OK` - успешное получение/обновление
- `201 Created` - создание ресурса (+ Location header)
- `400 Bad Request` - ошибка валидации
- `401 Unauthorized` - нет/невалидный токен
- `403 Forbidden` - нет прав доступа
- `404 Not Found` - ресурс не найден
- `422 Unprocessable Entity` - бизнес-логика запрещает операцию

**Важно:** Различие между 401 и 403 помогает frontend правильно реагировать (логин vs сообщение об ограничениях).

## Database Design

### Schema Design Principles

**1. Normalization:**
- 3NF для основных таблиц (companies, accounts, users)
- Денормализация для audit logs (полная копия данных на момент операции)

**2. Constraints:**
```sql
ALTER TABLE accounts
ADD CONSTRAINT fk_account_company
FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT;
```

**RESTRICT вместо CASCADE** для foreign keys - предотвращает случайное удаление связанных данных.

**3. Indexes:**
```sql
CREATE INDEX idx_company_inn ON companies(inn);
CREATE INDEX idx_account_company ON accounts(company_id);
CREATE INDEX idx_account_owner ON accounts(owner_id);
```

**Composite index** для частых запросов с фильтрацией:
```sql
CREATE INDEX idx_account_company_active ON accounts(company_id, is_active);
```

### Migration Strategy

**Doctrine Migrations** с version control:
- Каждая миграция в отдельном файле с timestamp
- Up/Down методы для rollback capability
- Миграции в git - часть кодовой базы

**Best practice:** Всегда делать backup перед миграцией в продакшене:
```bash
pg_dump -Fc factoring_db > backup_$(date +%Y%m%d_%H%M%S).dump
php bin/console doctrine:migrations:migrate
```

## Testing Strategy (рекомендации)

### Unit Tests

**Controller Tests:**
```php
class CompanyControllerTest extends WebTestCase
{
    public function testCreateCompanyRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/companies');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateCompanyValidatesINN(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $client->request('POST', '/api/companies', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Test', 'inn' => 'invalid'])
        );
        $this->assertResponseStatusCodeSame(400);
    }
}
```

### Integration Tests

**Repository Tests:**
- Тестирование сложных запросов с joins
- Проверка transaction boundaries
- Валидация constraints

### E2E Tests (опционально)

**Playwright/Cypress** для критичных user flows:
1. Логин → Создание компании → Создание счета
2. Попытка создать счет с чужим owner (должна fail)
3. Деактивация компании → проверка недоступности операций

## Performance Considerations

### Database Optimization

**1. Eager Loading:**
```php
$qb->select('c', 'accounts', 'users')
   ->from(Company::class, 'c')
   ->leftJoin('c.accounts', 'accounts')
   ->leftJoin('accounts.owner', 'users');
```
Вместо lazy loading - предотвращение N+1 queries.

**2. Pagination:**
```php
$qb->setFirstResult(($page - 1) * $limit)
   ->setMaxResults($limit);
```

**3. Query Result Cache** (для справочников):
```php
$query->useResultCache(true, 3600, 'company_types');
```

### Frontend Optimization

**1. Debouncing для search:**
```javascript
let searchTimeout;
searchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchCompanies(e.target.value);
    }, 300);
});
```

**2. Lazy loading для больших списков:**
```javascript
// Intersection Observer для подгрузки при скролле
const observer = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) loadMore();
});
```

**3. Request deduplication:**
```javascript
const pendingRequests = new Map();
async function fetchWithDedup(url) {
    if (pendingRequests.has(url)) {
        return pendingRequests.get(url);
    }
    const promise = fetch(url);
    pendingRequests.set(url, promise);
    return promise.finally(() => pendingRequests.delete(url));
}
```

## Monitoring & Observability

### Logging Strategy

**Structured Logging** через Monolog:
```php
$this->logger->info('Company created', [
    'company_id' => $company->getId(),
    'created_by' => $this->getUser()->getId(),
    'ip' => $request->getClientIp(),
]);
```

**Log Levels:**
- `ERROR` - исключения, требующие немедленного внимания
- `WARNING` - неоптимальные ситуации (slow queries, high memory usage)
- `INFO` - бизнес-события (создание компании, активация)
- `DEBUG` - детали для troubleshooting

### Audit Trail

**Separate audit table:**
```php
class AuditLog
{
    private string $entityType;
    private string $entityId;
    private string $action;  // created, updated, deleted, activated
    private array $changes;  // JSON diff
    private User $performedBy;
    private \DateTimeImmutable $performedAt;
}
```

**Причина:** Соответствие требованиям финансового регулирования (ЦБ РФ) о хранении истории операций.

## Scalability Considerations

### Horizontal Scaling

**Stateless API** - позволяет запускать несколько инстансов за load balancer:
```nginx
upstream factoring_api {
    server app1:8080;
    server app2:8080;
    server app3:8080;
}
```

**Session storage:** JWT tokens вместо server-side sessions - не требуется sticky sessions.

### Caching Strategy

**1. HTTP Cache (nginx):**
```nginx
location /api/companies {
    proxy_cache_valid 200 5m;
    proxy_cache_bypass $http_cache_control;
}
```

**2. Application Cache (Redis):**
```php
$companies = $cache->get('companies_list', function() {
    return $this->companyRepository->findAll();
});
```

**3. Query Result Cache (Doctrine):**
Для редко меняющихся справочников.

## Deployment Architecture

### Docker Setup

**Multi-stage build** для минимизации размера образа:
```dockerfile
FROM php:8.3-fpm AS base
# установка зависимостей

FROM base AS builder
# composer install

FROM base AS production
COPY --from=builder /app/vendor /app/vendor
```

### CI/CD Pipeline

**Рекомендуемый flow:**
1. Git push → GitHub Actions
2. Run tests (PHPUnit + static analysis)
3. Build Docker image
4. Push to registry
5. Deploy to staging
6. Manual approval
7. Deploy to production with blue-green deployment

## Возможные улучшения

### Short-term (можно добавить за 1-2 дня)

1. **Полнотекстовый поиск** через PostgreSQL tsvector:
```sql
ALTER TABLE companies ADD COLUMN search_vector tsvector;
CREATE INDEX idx_companies_search ON companies USING gin(search_vector);
```

2. **Batch operations** - массовая активация/деактивация компаний:
```php
#[Route('/api/companies/batch/activate', methods: ['POST'])]
public function batchActivate(Request $request): JsonResponse
```

3. **Export to Excel** через PhpSpreadsheet - для бухгалтерии.

4. **File uploads** для документов компании (устав, лицензии).

### Mid-term (неделя разработки)

1. **Event Sourcing для критичных операций** - хранение полной истории изменений счетов.

2. **WebSocket notifications** - real-time обновления при изменениях другими пользователями.

3. **Advanced filtering** - комплексные фильтры по множеству полей с сохранением presets.

4. **GraphQL API** как альтернатива REST - для сложных запросов с nested data.

### Long-term (месяц+)

1. **Multi-tenancy** - изоляция данных разных факторинговых компаний в одной инстанции.

2. **Microservices migration** - выделение Banking context в отдельный сервис.

3. **Machine Learning** - предсказание риска default'а на основе истории операций.

## Заключение

Архитектура построена с акцентом на:
- **Security first** - многоуровневая защита от атак и ошибок
- **Maintainability** - чистое разделение на слои и contexts
- **Scalability** - stateless design позволяет горизонтальное масштабирование
- **Compliance** - соответствие требованиям финансового регулирования

Использованы проверенные паттерны (DDD, REST, RBAC) вместо экспериментальных подходов - критично для финансовой системы. Frontend намеренно простой для снижения порога входа и ускорения разработки новых фич.

Система готова к продакшену после добавления rate limiting и настройки monitoring/alerting.
