# Factoring Platform

> 🇬🇧 [English version](./README.en.md)

Платформа для факторингового обслуживания с банковскими операциями, управлением пользователями и аудитом.

## 🚀 Возможности

### Identity Management
- ✅ Регистрация и аутентификация пользователей (JWT)
- ✅ Система ролей и прав доступа (RBAC)
- ✅ Управление пользователями (блокировка, редактирование)
- ✅ **Управление компаниями** (создание, редактирование, активация/деактивация)
- ✅ Мульти-тенантность (разделение по компаниям)
- ✅ Типы компаний: Фактор, Поставщик, Дебитор

### Banking & Accounts
- ✅ **Управление счетами** (создание, просмотр, корректировка балансов)
- ✅ Банковские переводы между счетами
- ✅ Контроль прав доступа к счетам (Voters)
- ✅ Поддержка нескольких валют (RUB, USD, EUR)
- ✅ Проверка достаточности средств
- ✅ История операций

### Audit & Security
- ✅ Полный аудит всех операций
- ✅ Запись в журнал переводов, изменений пользователей
- ✅ API Keys для программного доступа
- ✅ Шифрование паролей (bcrypt)
- ✅ JWT токены с истечением

### Admin Panel
- ✅ Управление пользователями
- ✅ **Управление компаниями и счетами** (новое!)
- ✅ Просмотр журнала аудита
- ✅ Блокировка/разблокировка пользователей
- ✅ Назначение ролей

## 🆕 Новая функциональность: Управление компаниями

Администраторы теперь могут:

### Через веб-интерфейс (/admin-companies.html):
- 🏢 Создавать новые компании (поставщики, дебиторы, факторы)
- 📝 Редактировать информацию о компаниях
- 💳 Создавать счета для компаний
- 👥 Назначать владельцев счетов
- 💰 Корректировать балансы (административные операции)
- 📊 Фильтровать компании по типам
- ✅ Активировать/деактивировать компании

### Через API:
- `POST /api/companies` - Создание компании
- `GET /api/companies` - Список компаний (с фильтрами)
- `PUT /api/companies/{id}` - Редактирование
- `POST /api/accounts` - Создание счета для компании
- `GET /api/accounts?companyId=xxx` - Счета компании

📖 Подробности: [COMPANY_MANAGEMENT_GUIDE.md](./COMPANY_MANAGEMENT_GUIDE.md)
⚡ Быстрый старт: [COMPANY_MANAGEMENT_QUICKSTART.md](./COMPANY_MANAGEMENT_QUICKSTART.md)

## 🛠 Технологии

- **Backend:** Symfony 7.2, PHP 8.3
- **Database:** PostgreSQL 16
- **Message Queue:** RabbitMQ (via Symfony Messenger)
- **Cache / Idempotency:** Redis
- **Frontend:** Tabler UI, Vanilla JavaScript
- **Authentication:** JWT (LexikJWTAuthenticationBundle)
- **ORM:** Doctrine

## 🏗 Архитектура

### Модульный монолит (DDD)

Система организована по принципам **Domain-Driven Design** с тремя явными bounded contexts:

| Контекст | Путь | Ответственность |
|---|---|---|
| **Identity** | `src/Identity/` | Пользователи, компании, роли, аутентификация |
| **Banking** | `src/Banking/` | Счета, переводы, финансовые запросы |
| **Shared** | `src/Shared/` | Аудит-логи, общие value objects |

Границы между контекстами соблюдаются жёстко: Banking знает об Identity только через идентификаторы (UUID), но не через прямые зависимости между сущностями.

### Асинхронная обработка переводов

```
POST /api/v2/transactions/transfer
        │
        │  202 Accepted  ←── ответ клиенту возвращается немедленно
        ▼
  MessageBus → RabbitMQ (очередь)
        │
        │  messenger:consume async (фоновый воркер)
        ▼
  TransferMoneyHandler:
    1. Проверка ключа идемпотентности (Redis)  ← защита от дублей
    2. Сортировка UUID счетов                   ← защита от дедлоков
    3. SELECT … FOR UPDATE на оба счёта         ← пессимистическая блокировка
    4. Списание / зачисление в транзакции
    5. Запись ключа идемпотентности (Redis, TTL 24ч)
```

**Почему 202, а не 200?** Перевод обрабатывается асинхронно — результат можно получить через `GET /api/v2/transactions/{id}`.

### Хранение денег

Все суммы хранятся как **целые числа (центы/копейки)** в `BIGINT`:

```
1 000.50 RUB  →  сохраняется как  100050
```

Это исключает ошибки с плавающей точкой (0.1 + 0.2 ≠ 0.3) при финансовых расчётах.

### Защита от дедлоков

При одновременных переводах A→B и B→A оба обработчика всегда блокируют счета **в одном порядке** (по возрастанию UUID). Это гарантирует отсутствие circular wait:

```
Перевод A→B:  lock(min(A,B)) → lock(max(A,B))
Перевод B→A:  lock(min(A,B)) → lock(max(A,B))  ← тот же порядок
```

### Ключевые архитектурные решения

| Решение | Обоснование |
|---|---|
| **Soft delete компаний** (`is_active`) | Сохранение истории операций и финансовых связей |
| **Отдельный endpoint `/activate`** вместо PATCH | Активация — бизнес-операция с потенциальными side-effects |
| **DTO вместо прямой десериализации** в Entity | Защита от mass assignment уязвимостей |
| **JWT security versioning** (поле `security_version`) | Мгновенная инвалидация токенов при смене пароля/блокировке |
| **Optimistic locking** (поле `version`) | Защита от потери изменений при конкурентных обновлениях |
| **FK RESTRICT** вместо CASCADE | Предотвращение случайного удаления связанных финансовых данных |
| **Vanilla JS** вместо React/Vue | Нет build-процесса; любой backend-разработчик может вносить правки |

### Безопасность API

- **401 vs 403**: различаются намеренно — 401 означает «войдите», 403 — «у вас нет прав»
- **IDOR защита**: `/api/admin/users?companyId=X` требует явной проверки роли
- **XSS**: все данные экранируются через `element.textContent` перед вставкой в DOM
- **CSRF**: JWT передаётся в `Authorization` заголовке, не в cookie — браузер не отправляет его автоматически

> 📐 Подробные диаграммы: [ARCHITECTURE_DIAGRAM.md](./ARCHITECTURE_DIAGRAM.md)
> 🔍 Архитектурные решения: [ARCHITECTURE.md](./ARCHITECTURE.md)

## 📁 Структура проекта

```
factoring-core/
├── src/
│   ├── Identity/           # Управление пользователями и компаниями
│   │   ├── Controller/
│   │   │   ├── CompanyController.php        # 🆕 Управление компаниями
│   │   │   ├── UserManagementController.php
│   │   │   ├── AuthController.php
│   │   │   └── ...
│   │   ├── Entity/
│   │   │   ├── User.php
│   │   │   ├── Company.php
│   │   │   ├── Role.php
│   │   │   └── ...
│   │   └── Repository/
│   │
│   ├── Banking/            # Банковские операции
│   │   ├── Controller/
│   │   │   ├── AccountController.php        # 🆕 Управление счетами
│   │   │   ├── BankAccountController.php
│   │   │   ├── TransferController.php
│   │   │   └── ...
│   │   ├── Entity/
│   │   │   ├── Account.php
│   │   │   ├── BankAccount.php
│   │   │   ├── Transaction.php
│   │   │   └── ...
│   │   └── Security/
│   │       ├── AccountVoter.php            # Контроль доступа
│   │       └── BankAccountVoter.php
│   │
│   └── Shared/             # Общая функциональность
│       ├── Entity/
│       │   └── AuditLog.php
│       └── ...
│
├── public/
│   ├── admin-companies.html    # 🆕 Управление компаниями
│   ├── admin-users.html        # Управление пользователями
│   ├── banking.html            # Банковские операции
│   ├── profile.html            # Профиль пользователя
│   ├── audit-logs.html         # Журнал аудита
│   └── ...
│
├── migrations/             # Миграции базы данных
├── config/                 # Конфигурация Symfony
└── docker/                 # Docker конфигурация
```

## 🚀 Быстрый старт

### Требования
- Docker и Docker Compose
- Make (опционально)

### Установка

```bash
# Клонировать репозиторий
git clone <repo-url>
cd factoring-core

# Запустить контейнеры
docker compose up -d

# Установить зависимости
docker compose exec php composer install

# Выполнить миграции
docker compose exec php bin/console doctrine:migrations:migrate

# (Опционально) Загрузить тестовые данные
docker compose exec php bin/console doctrine:fixtures:load
```

### Доступ

- **Приложение:** http://localhost:8080
- **База данных:** localhost:5432 (factoring/app/app)

### Тестовые пользователи

После загрузки fixtures доступны:

**Администратор:**
- Email: admin@finansfactor.ru
- Роли: ROLE_ADMIN, ROLE_USER

**Менеджер:**
- Email: manager@finansfactor.ru
- Роли: ROLE_MANAGER, ROLE_USER

**Обычный пользователь:**
- Email: user@supplier.ru
- Роли: ROLE_USER

*Пароли уточняйте в fixtures или создайте новых пользователей*

## 📖 API Документация

### Authentication

```bash
# Регистрация
POST /api/auth/register
{
  "email": "user@example.com",
  "password": "Password123!",
  "passwordConfirm": "Password123!",
  "firstName": "Иван",
  "lastName": "Иванов",
  "phone": "+79991234567",
  "companyId": "uuid-компании"
}

# Вход
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "Password123!"
}
# Ответ: { "token": "jwt-token", "user": {...} }
```

### Companies (ROLE_ADMIN)

```bash
# Список компаний
GET /api/companies
GET /api/companies?type=supplier
GET /api/companies?status=active

# Создать компанию
POST /api/companies
{
  "name": "ООО Компания",
  "inn": "1234567890",
  "type": "supplier",
  "legalAddress": "Адрес..."
}

# Редактировать
PUT /api/companies/{id}

# Активировать/деактивировать
POST /api/companies/{id}/activate
POST /api/companies/{id}/deactivate
```

### Accounts (ROLE_ADMIN)

```bash
# Список счетов
GET /api/accounts
GET /api/accounts?companyId=xxx

# Создать счет
POST /api/accounts
{
  "name": "Операционный счет",
  "companyId": "uuid",
  "ownerId": "uuid-пользователя",
  "balance": 100000,
  "currency": "RUB"
}

# Обновить баланс
PUT /api/accounts/{id}
{
  "balance": 250000
}

# Удалить (только с балансом 0)
DELETE /api/accounts/{id}
```

### Banking

```bash
# Мои счета
GET /api/users/me/accounts

# Перевод
POST /api/banking/transfer
{
  "fromId": "uuid-счета-отправителя",
  "toId": "uuid-счета-получателя",
  "amount": 1000,
  "purpose": "Оплата по договору"
}
```

### Audit Logs

```bash
# Просмотр логов
GET /api/audit-logs?limit=50&offset=0
GET /api/audit-logs?entityType=User
GET /api/audit-logs?action=CREATE
```

## 🔐 Безопасность

### Роли

- `ROLE_USER` - Базовая роль (все пользователи)
- `ROLE_MANAGER` - Менеджер (расширенный доступ)
- `ROLE_ADMIN` - Администратор (полный доступ)
- `ROLE_AUDIT_VIEWER` - Просмотр аудит-логов

### Voters (Access Control)

**AccountVoter:**
- `VIEW` - Просмотр счета (владелец или админ)
- `TRANSFER_FROM` - Перевод со счета (владелец или компания)

**BankAccountVoter:**
- Аналогично для полных банковских счетов

### JWT Tokens

- Время жизни: настраивается в .env
- Автоматическое обновление через refresh token (опционально)
- Хранение в localStorage на клиенте

## 🧪 Тестирование

### Backend (PHPUnit)

```bash
docker compose exec php bin/phpunit
```

### API тесты

```bash
# Полный цикл создания компании и счета
./test-company-creation.sh
```

### Ручное тестирование

1. Откройте http://localhost:8080/admin-companies.html
2. Войдите под администратором
3. Создайте тестовую компанию
4. Зарегистрируйте пользователя для этой компании
5. Создайте счет для компании
6. Выполните тестовый перевод в banking.html

## 📊 Мониторинг

### Логи

```bash
# PHP логи
docker compose logs php -f

# Nginx логи
docker compose logs nginx -f

# База данных
docker compose logs database -f
```

### База данных

```bash
# Подключиться к PostgreSQL
docker compose exec database psql -U app -d factoring

# Основные таблицы
\dt

# Проверить компании
SELECT * FROM companies;

# Проверить счета
SELECT * FROM banking_accounts;

# Проверить аудит-логи
SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10;
```

## 🐛 Отладка

### Очистка кэша

```bash
docker compose exec php bin/console cache:clear
```

### Просмотр маршрутов

```bash
docker compose exec php bin/console debug:router
docker compose exec php bin/console debug:router api_companies_list
```

### Проверка конфигурации

```bash
docker compose exec php bin/console debug:config
docker compose exec php bin/console debug:container
```

## 📝 Roadmap

### v1.1 (Текущая версия)
- ✅ Управление компаниями через UI
- ✅ Создание счетов через UI
- ✅ Административные корректировки балансов
- ✅ Фильтрация и поиск компаний

### v1.2 (Планируется)
- ⏳ Валидация ИНН через ФНС API
- ⏳ Типы счетов (операционный, резервный, транзитный)
- ⏳ Лимиты на счета и операции
- ⏳ Workflow одобрения новых компаний
- ⏳ Экспорт данных в Excel/PDF
- ⏳ Расширенная аналитика

### v2.0 (Будущее)
- ⏳ Факторинговые операции (уступка требований)
- ⏳ Интеграция с бухгалтерскими системами
- ⏳ Автоматические расчеты комиссий
- ⏳ Уведомления (email, SMS, push)
- ⏳ Мобильное приложение
- ⏳ Двухфакторная аутентификация (2FA)

## 🤝 Вклад в проект

1. Fork репозитория
2. Создайте feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit изменений (`git commit -m 'Add AmazingFeature'`)
4. Push в branch (`git push origin feature/AmazingFeature`)
5. Откройте Pull Request

## 📄 Лицензия

Proprietary - Все права защищены

## 📞 Контакты

Для вопросов и поддержки: support@finansfactor.ru

---

**Последнее обновление:** 28 декабря 2024
**Версия:** 1.1.0
