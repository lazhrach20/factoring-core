# Factoring Platform

> 🇷🇺 [Русская версия](./README.md)

MVP demo of a factoring platform with banking operations, user management, and audit logging.

## 🚀 Features

### Identity Management
- ✅ User registration and authentication (JWT)
- ✅ Role-based access control (RBAC)
- ✅ User management (block, edit)
- ✅ Company management (create, edit, activate/deactivate)
- ✅ Multi-tenancy (company-scoped data isolation)
- ✅ Company types: Factor, Supplier, Debtor

### Banking & Accounts
- ✅ Account management (create, view, balance adjustments)
- ✅ Bank transfers between accounts
- ✅ Account-level access control (Voters)
- ✅ Multi-currency support (RUB, USD, EUR)
- ✅ Insufficient funds validation
- ✅ Transaction history

### Financing Requests
- ✅ Suppliers can submit financing requests
- ✅ Factor company approves/rejects requests
- ✅ Automatic fund transfer on approval
- ✅ Async processing via message queue (RabbitMQ)

### Audit & Security
- ✅ Full audit trail of all operations
- ✅ Transfer and user-change logging
- ✅ API Keys for programmatic access
- ✅ Password hashing (bcrypt)
- ✅ JWT tokens with expiry and security versioning

### Admin Panel
- ✅ User management
- ✅ Company and account management
- ✅ Audit log viewer
- ✅ Block/unblock users
- ✅ Role assignment

## 🛠 Tech Stack

- **Backend:** Symfony 7.2, PHP 8.3
- **Database:** PostgreSQL 16
- **Message Queue:** RabbitMQ (via Symfony Messenger)
- **Cache / Idempotency:** Redis
- **Frontend:** Tabler UI, Vanilla JavaScript
- **Authentication:** JWT (LexikJWTAuthenticationBundle)
- **ORM:** Doctrine

## 🏗 Architecture

### Modular Monolith (DDD)

The system is organized around **Domain-Driven Design** principles with three explicit bounded contexts:

| Context | Path | Responsibility |
|---|---|---|
| **Identity** | `src/Identity/` | Users, companies, roles, authentication |
| **Banking** | `src/Banking/` | Accounts, transfers, financing requests |
| **Shared** | `src/Shared/` | Audit logs, shared value objects |

Context boundaries are kept strict: Banking references Identity only via UUIDs, never via direct entity dependencies.

### Async Transfer Processing

```
POST /api/v2/transactions/transfer
        │
        │  202 Accepted  ←── client gets response immediately
        ▼
  MessageBus → RabbitMQ (queue)
        │
        │  messenger:consume async (background worker)
        ▼
  TransferMoneyHandler:
    1. Check idempotency key (Redis)      ← duplicate protection
    2. Sort account UUIDs ascending       ← deadlock prevention
    3. SELECT … FOR UPDATE on both        ← pessimistic locking
    4. Debit / credit inside a transaction
    5. Store idempotency key (Redis, TTL 24h)
```

**Why 202 instead of 200?** Transfers are processed asynchronously — poll the result via `GET /api/v2/transactions/{id}`.

### Money Representation

All amounts are stored as **integer cents** in `BIGINT`:

```
1 000.50 RUB  →  stored as  100050
```

This eliminates floating-point precision errors (0.1 + 0.2 ≠ 0.3) in financial calculations.

### Deadlock Prevention

For concurrent transfers A→B and B→A, both workers always lock accounts in the **same order** (ascending UUID). This guarantees no circular wait:

```
Transfer A→B:  lock(min(A,B)) → lock(max(A,B))
Transfer B→A:  lock(min(A,B)) → lock(max(A,B))  ← same order
```

### Key Architectural Decisions

| Decision | Rationale |
|---|---|
| **Soft delete for companies** (`is_active`) | Preserves transaction history and financial relationships |
| **Separate `/activate` endpoint** instead of PATCH | Activation is a business operation with potential side-effects |
| **DTOs instead of direct entity deserialization** | Prevents mass assignment vulnerabilities |
| **JWT security versioning** (`security_version` field) | Instantly invalidates tokens on password change or account block |
| **Optimistic locking** (`version` field) | Prevents lost updates under concurrent modifications |
| **FK RESTRICT** instead of CASCADE | Prevents accidental deletion of linked financial data |
| **Vanilla JS** instead of React/Vue | No build process; any backend developer can make changes |

### API Security

- **401 vs 403**: intentionally distinct — 401 means "please log in", 403 means "you don't have permission"
- **IDOR protection**: `/api/admin/users?companyId=X` requires explicit role check
- **XSS**: all data sanitized via `element.textContent` before DOM insertion
- **CSRF**: JWT is sent in the `Authorization` header, not a cookie — browsers never send it automatically

> 📐 Visual diagrams: [ARCHITECTURE_DIAGRAM.md](./ARCHITECTURE_DIAGRAM.md)
> 🔍 Detailed decisions: [ARCHITECTURE.md](./ARCHITECTURE.md)

## 📁 Project Structure

```
factoring-core/
├── src/
│   ├── Identity/           # Users, companies, auth
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Service/
│   │   └── Repository/
│   ├── Banking/            # Accounts, transactions, financing
│   │   ├── Controller/
│   │   ├── Entity/
│   │   ├── Message/
│   │   ├── MessageHandler/
│   │   ├── Service/
│   │   └── Repository/
│   ├── Shared/             # Audit logs, shared value objects
│   └── Security/           # Voters, JWT listeners
├── public/
│   ├── admin-companies.html
│   ├── admin-users.html
│   ├── banking.html
│   ├── financing-requests.html
│   ├── profile.html
│   ├── audit-logs.html
│   └── js/
│       └── navigation.js   # Shared navigation component
├── migrations/
├── config/
└── docker/
```

## 🚀 Quick Start

### Requirements
- Docker and Docker Compose

### Setup

```bash
# Clone the repository
git clone <repo-url>
cd factoring-core

# Copy environment file and set your secrets
cp .env.example .env.local
# Edit .env.local with your passwords

# Start containers
docker compose up -d

# Install dependencies
docker compose exec php composer install

# Generate JWT keys
docker compose exec php bin/console lexik:jwt:generate-keypair

# Run migrations
docker compose exec php bin/console doctrine:migrations:migrate

# Load demo data (optional)
docker compose exec php bin/console doctrine:fixtures:load
```

### Access

- **Application:** http://localhost:8080
- **RabbitMQ UI:** http://localhost:15672

## 🧑‍💻 Demo Accounts

After loading fixtures (`doctrine:fixtures:load`):

| Email | Password | Role |
|---|---|---|
| `admin@finansfactor.ru` | `admin123` | Factor / Admin |
| `accountant@finansfactor.ru` | `accountant123` | Factor / Accountant |
| `owner@supplier.ru` | `supplier123` | Supplier |
| `finance@debtor.ru` | `debtor123` | Debtor |

## 📖 API Reference

### Authentication

```bash
# Login
POST /api/auth/login
{"email": "admin@finansfactor.ru", "password": "admin123"}
# → {"token": "...jwt..."}

# Current user
GET /api/auth/me
Authorization: Bearer <token>
```

### Companies (ROLE_ADMIN)

```bash
GET  /api/companies
GET  /api/companies?type=supplier&status=active
POST /api/companies
PUT  /api/companies/{id}
POST /api/companies/{id}/activate
POST /api/companies/{id}/deactivate
```

### Accounts

```bash
GET  /api/users/me/accounts       # My accounts
GET  /api/accounts?companyId=xxx  # Company accounts (admin)
POST /api/accounts                # Create account (admin)
```

### Transactions

```bash
POST /api/v2/transactions/deposit      # Deposit (async)
POST /api/v2/transactions/transfer     # Transfer between accounts
GET  /api/v2/transactions?limit=50     # Transaction history
GET  /api/v2/transactions/{id}         # Transaction status
```

### Financing Requests

```bash
POST /api/financing-requests           # Submit request (supplier)
GET  /api/financing-requests           # List requests
POST /api/financing-requests/{id}/approve  # Approve (factor)
POST /api/financing-requests/{id}/reject   # Reject (factor)
```

### Audit Logs (ROLE_ADMIN)

```bash
GET /api/audit-logs
GET /api/audit-logs?entityType=User&action=CREATE
```

## 🔐 Security

### Roles

| Role | Description |
|---|---|
| `ROLE_USER` | Default role for all users |
| `ROLE_ACCOUNTANT` | Financial operations |
| `ROLE_ADMIN` | Full system access |
| `ROLE_AUDIT_VIEWER` | Read-only audit log access |

### Access Control (Voters)
- Account operations are controlled by `AccountVoter` — only the account owner or an admin can transfer from an account.

### JWT Security Versioning
- A `security_version` field on the user is incremented on password change or account block, instantly invalidating all existing tokens.

## 🧪 Testing

```bash
# Run PHPUnit tests
docker compose exec php bin/phpunit

# Run only unit tests
docker compose exec php bin/phpunit tests/Unit

# Run only integration tests
docker compose exec php bin/phpunit tests/Integration
```

## 🐛 Debugging

```bash
# Clear cache
docker compose exec php bin/console cache:clear

# List routes
docker compose exec php bin/console debug:router

# View logs
docker compose logs php -f
docker compose logs nginx -f
```

## 📝 Roadmap

### v1.1 (Current)
- ✅ Company management UI
- ✅ Account management UI
- ✅ Financing requests
- ✅ Async transaction processing
- ✅ JWT security versioning

### v1.2 (Planned)
- ⏳ Email notifications (password reset, transaction alerts)
- ⏳ INN validation via FTS API
- ⏳ Account limits and operation caps
- ⏳ Company onboarding workflow
- ⏳ Excel/PDF export

### v2.0 (Future)
- ⏳ Full factoring workflow (invoice assignment)
- ⏳ Accounting system integration
- ⏳ Automatic commission calculation
- ⏳ Two-factor authentication (2FA)
- ⏳ Mobile application


