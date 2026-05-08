# Управление компаниями и счетами - Быстрая шпаргалка

## 🌐 Веб-интерфейс

**URL:** http://localhost:8080/admin-companies.html

**Требования:** Роль `ROLE_ADMIN`

### Быстрые действия:

1. **Создать компанию:** Кнопка "Создать компанию" → Заполнить форму → Создать
2. **Создать счет:** Найти компанию → "+ Счет" → Выбрать владельца → Создать
3. **Просмотр счетов:** "👁 Счета" на карточке компании
4. **Фильтрация:** Вкладки "Все" / "Поставщики" / "Дебиторы" / "Факторы"

## 🔑 API - Быстрый старт

### 1. Получить токен

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ADMIN_EMAIL","password":"PASSWORD"}' \
  | python3 -c "import sys, json; print(json.load(sys.stdin)['token'])")
```

### 2. Создать компанию

```bash
curl -X POST http://localhost:8080/api/companies \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "ООО Компания",
    "inn": "1234567890",
    "type": "supplier"
  }'
```

### 3. Создать счет

```bash
curl -X POST http://localhost:8080/api/accounts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Операционный счет",
    "companyId": "COMPANY_UUID",
    "ownerId": "USER_UUID",
    "balance": 100000,
    "currency": "RUB"
  }'
```

## 📊 Основные endpoints

| Действие | Method | URL |
|----------|--------|-----|
| Список компаний | GET | `/api/companies` |
| Создать компанию | POST | `/api/companies` |
| Список счетов | GET | `/api/accounts?companyId=XXX` |
| Создать счет | POST | `/api/accounts` |
| Пользователи компании | GET | `/api/admin/users?companyId=XXX` |

## ⚠️ Важно помнить

- ✅ Только `ROLE_ADMIN` имеет доступ
- ✅ ИНН должен быть уникальным
- ✅ Владелец счета должен быть из той же компании
- ✅ Невозможно удалить счет с балансом > 0
- ✅ Типы компаний: `supplier`, `debtor`, `factor`
- ✅ Валюты: `RUB`, `USD`, `EUR`

## 🎯 Типичный flow

```
1. Создать компанию (POST /api/companies)
   ↓
2. Зарегистрировать пользователя (POST /api/auth/register с companyId)
   ↓
3. Создать счет (POST /api/accounts с ownerId пользователя)
   ↓
4. Готово! Компания может работать
```

## 🔍 Проверка в БД

```sql
-- Все компании
SELECT name, inn, type, status FROM companies;

-- Счета компании
SELECT a.name, a.balance, a.currency, c.name as company
FROM banking_accounts a
JOIN companies c ON a.company_id = c.id
WHERE c.inn = '1234567890';

-- Пользователи компании
SELECT u.email, u.first_name, u.last_name, c.name
FROM users u
JOIN companies c ON u.company_id = c.id
WHERE c.inn = '1234567890';
```

## 🚀 Быстрый тест

```bash
# 1. Получить токен админа
TOKEN="ваш_токен"

# 2. Создать компанию
COMPANY=$(curl -s -X POST http://localhost:8080/api/companies \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Co","inn":"9999999999","type":"supplier"}')

COMPANY_ID=$(echo $COMPANY | python3 -c "import sys,json; print(json.load(sys.stdin)['company']['id'])")

# 3. Зарегистрировать пользователя
USER=$(curl -s -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email":"test@test.com",
    "password":"Test123!",
    "passwordConfirm":"Test123!",
    "firstName":"Test",
    "lastName":"User",
    "phone":"+79991234567",
    "companyId":"'$COMPANY_ID'"
  }')

USER_ID=$(echo $USER | python3 -c "import sys,json; print(json.load(sys.stdin)['user']['id'])")

# 4. Создать счет
curl -X POST http://localhost:8080/api/accounts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Main Account",
    "companyId":"'$COMPANY_ID'",
    "ownerId":"'$USER_ID'",
    "balance":50000,
    "currency":"RUB"
  }'

echo "✅ Компания, пользователь и счет созданы!"
```

## 📖 Полная документация

См. [COMPANY_MANAGEMENT_GUIDE.md](./COMPANY_MANAGEMENT_GUIDE.md)
