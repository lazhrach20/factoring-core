# System Architecture Diagram

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         CLIENT (Mobile/Web/API)                          │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ HTTP POST
                                    │ /api/banking/transfer
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         SYMFONY APPLICATION                              │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  TransferController (API Layer)                                   │  │
│  │  • Validate request                                               │  │
│  │  • Return 202 Accepted immediately                                │  │
│  └────────────────────────┬──────────────────────────────────────────┘  │
│                           │ dispatch(TransferMoney)                     │
│                           ▼                                             │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  MessageBusInterface (Symfony Messenger)                          │  │
│  │  • Route message to async transport                               │  │
│  └────────────────────────┬──────────────────────────────────────────┘  │
└─────────────────────────────┼──────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          RABBITMQ (Message Queue)                        │
│  • Persist messages to disk                                              │
│  • Ensure reliable delivery                                              │
│  • Support multiple workers                                              │
└─────────────────────────────┬───────────────────────────────────────────┘
                              │
                              │ messenger:consume async
                              ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    MESSENGER WORKER (Background Process)                 │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │  TransferMoneyHandler                                             │  │
│  │  • Check idempotency (Redis)                                      │  │
│  │  • Start database transaction                                     │  │
│  │  • Lock accounts (deadlock prevention)                            │  │
│  │  • Perform transfer                                                │  │
│  │  • Commit transaction                                              │  │
│  │  • Store idempotency key (Redis)                                  │  │
│  └────────────────────────┬──────────────────────────────────────────┘  │
└─────────────────────────────┼──────────────────────────────────────────┘
                              │
            ┌─────────────────┼─────────────────┐
            │                 │                 │
            ▼                 ▼                 ▼
┌──────────────────┐  ┌──────────────┐  ┌──────────────┐
│   POSTGRESQL     │  │    REDIS     │  │   LOGGING    │
│                  │  │              │  │              │
│ • Accounts       │  │ • Idempotency│  │ • Monolog    │
│ • Transactions   │  │   Keys       │  │ • Error      │
│ • Locks (FOR     │  │ • Expiration │  │   Tracking   │
│   UPDATE)        │  │   (24h)      │  │              │
└──────────────────┘  └──────────────┘  └──────────────┘
```

---

## Transfer Flow Diagram

```
┌────────────┐
│   Client   │
└──────┬─────┘
       │
       │ 1. POST /api/banking/transfer
       │    { fromId, toId, amount }
       ▼
┌──────────────────┐
│ TransferController│
└──────┬───────────┘
       │
       │ 2. Validate input
       │    • Valid UUIDs?
       │    • Amount > 0?
       │    • Different accounts?
       ▼
┌──────────────────┐
│  Create Message  │
│  TransferMoney   │
└──────┬───────────┘
       │
       │ 3. Dispatch to MessageBus
       ▼
┌──────────────────┐
│   MessageBus     │
└──────┬───────────┘
       │
       │ 4. Route to 'async' transport
       ▼
┌──────────────────┐
│    RabbitMQ      │ ◄─── Message persisted to disk
└──────┬───────────┘
       │
       │ 5. Return 202 Accepted
       │    { status: "accepted", idempotencyKey: "..." }
       ▼
┌────────────┐
│   Client   │ ◄─── Response received immediately
└────────────┘

       ... (Async Processing) ...

┌──────────────────┐
│  Worker Process  │ ◄─── messenger:consume async
└──────┬───────────┘
       │
       │ 6. Consume message from RabbitMQ
       ▼
┌──────────────────────┐
│ TransferMoneyHandler │
└──────┬───────────────┘
       │
       │ 7. Check Redis for idempotency key
       ▼
    ┌─────────────────┐
    │ Already exists? │
    └───┬─────────┬───┘
        │ Yes     │ No
        │         │
        │         │ 8. wrapInTransaction()
        │         ▼
        │    ┌─────────────────────┐
        │    │ Sort account IDs    │ ◄─── Deadlock prevention
        │    └──────┬──────────────┘
        │           │
        │           │ 9. Lock accounts in order
        │           ▼
        │    ┌─────────────────────┐
        │    │ SELECT ... FOR      │
        │    │ UPDATE (Account 1)  │
        │    └──────┬──────────────┘
        │           │
        │           ▼
        │    ┌─────────────────────┐
        │    │ SELECT ... FOR      │
        │    │ UPDATE (Account 2)  │
        │    └──────┬──────────────┘
        │           │
        │           │ 10. Validate & Transfer
        │           ▼
        │    ┌─────────────────────┐
        │    │ fromAccount.        │
        │    │   withdraw(amount)  │
        │    └──────┬──────────────┘
        │           │
        │           ▼
        │    ┌─────────────────────┐
        │    │ toAccount.          │
        │    │   deposit(amount)   │
        │    └──────┬──────────────┘
        │           │
        │           │ 11. Commit transaction
        │           ▼
        │    ┌─────────────────────┐
        │    │ Store idempotency   │
        │    │ key in Redis        │
        │    └──────┬──────────────┘
        │           │
        └───────────┴─────► Success! ✓
```

---

## Deadlock Prevention Strategy

### Without Sorting (❌ Deadlock Risk)

```
Time    Transaction A                  Transaction B
─────   ──────────────────            ──────────────────
t0      Lock Account 1                 Lock Account 2
t1      Wait for Account 2             Wait for Account 1
t2      ⏳ Waiting...                  ⏳ Waiting...
t3      ⏳ Waiting...                  ⏳ Waiting...
t4      💥 DEADLOCK!                   💥 DEADLOCK!
```

### With Sorting (✅ No Deadlock)

```
Time    Transaction A                  Transaction B
─────   ──────────────────            ──────────────────
t0      Sort: [Acc1, Acc2]            Sort: [Acc1, Acc2]
t1      Lock Account 1 ✓              Wait for Account 1
t2      Lock Account 2 ✓              ⏳ Waiting...
t3      Process transfer              ⏳ Waiting...
t4      Commit & Release              ⏳ Waiting...
t5      ✅ Complete                    Lock Account 1 ✓
t6                                    Lock Account 2 ✓
t7                                    Process transfer
t8                                    Commit & Release
t9                                    ✅ Complete
```

---

## Database Locking Mechanism

```
PostgreSQL Transaction Isolation

┌─────────────────────────────────────────────────────────────┐
│  Transaction A                  Transaction B                │
├─────────────────────────────────────────────────────────────┤
│  BEGIN;                         BEGIN;                       │
│                                                              │
│  SELECT * FROM accounts         SELECT * FROM accounts       │
│  WHERE id = 'uuid-1'            WHERE id = 'uuid-1'          │
│  FOR UPDATE;                    FOR UPDATE;                  │
│  ✓ Lock acquired               ⏳ WAITING (blocked)         │
│                                                              │
│  UPDATE accounts                                             │
│  SET balance = ...              ⏳ Still waiting...          │
│  WHERE id = 'uuid-1';                                        │
│                                                              │
│  COMMIT;                        ⏳ Still waiting...          │
│  ✓ Lock released               ✓ Lock acquired!            │
│                                                              │
│                                UPDATE accounts                │
│                                SET balance = ...              │
│                                WHERE id = 'uuid-1';           │
│                                                              │
│                                COMMIT;                        │
│                                ✓ Lock released               │
└─────────────────────────────────────────────────────────────┘
```

---

## Module Structure (Modular Monolith)

```
src/
├── Banking/                    # Banking Bounded Context
│   ├── Controller/
│   │   └── TransferController.php
│   ├── Entity/
│   │   └── Account.php
│   ├── Repository/
│   │   └── AccountRepository.php
│   ├── Message/
│   │   └── TransferMoney.php
│   ├── MessageHandler/
│   │   └── TransferMoneyHandler.php
│   └── Service/
│       └── (future services)
│
├── Identity/                   # Identity Bounded Context
│   ├── Entity/
│   │   ├── User.php
│   │   └── Role.php
│   └── ...
│
└── Shared/                     # Shared Kernel
    ├── ValueObject/
    │   ├── Money.php
    │   └── Currency.php
    └── ...

Benefits:
✅ Clear boundaries between domains
✅ Easy to extract to microservices later
✅ Follows Domain-Driven Design
✅ Better maintainability
```

---

## Data Flow: Money Representation

```
┌──────────────┐
│ User Input   │
│ "100.50 RUB" │
└──────┬───────┘
       │
       │ Convert to cents
       │ 100.50 × 100 = 10050
       ▼
┌──────────────┐
│ API Request  │
│ amount: 10050│ ◄─── Integer (cents)
└──────┬───────┘
       │
       │ Store in DB
       ▼
┌──────────────┐
│ PostgreSQL   │
│ BIGINT: 10050│ ◄─── Safe for large numbers
└──────┬───────┘
       │
       │ Read from DB
       ▼
┌──────────────┐
│ Application  │
│ int: 10050   │
└──────┬───────┘
       │
       │ Display to user
       │ 10050 ÷ 100 = 100.50
       ▼
┌──────────────┐
│ User Display │
│ "100.50 RUB" │
└──────────────┘

Why BigInt (cents)?
✅ No floating-point errors
✅ Precise calculations
✅ Supports large amounts
✅ Database-friendly
```

---

## Error Handling Flow

```
┌─────────────┐
│   Request   │
└──────┬──────┘
       │
       ▼
   Validation
       │
       ├─── ❌ Invalid → 400 Bad Request
       │
       ▼
   Dispatch Message
       │
       ▼
   RabbitMQ Queue
       │
       ▼
   Handler Processing
       │
       ├─── ❌ Business Error → Retry (3x)
       │                        │
       │                        └─── ❌ Still failing
       │                                    │
       │                                    ▼
       │                              Failed Queue
       │                                    │
       │                                    ▼
       │                              Manual Review
       │
       ├─── ❌ Technical Error → Retry (3x)
       │
       └─── ✅ Success → Commit & Log
```

---

## Scalability Strategy

```
        ┌────────────────┐
        │  Load Balancer │
        └────────┬───────┘
                 │
        ┌────────┴────────┐
        │                 │
┌───────▼──────┐  ┌───────▼──────┐
│   API Node 1 │  │   API Node 2 │  ◄─── Horizontal scaling
└───────┬──────┘  └───────┬──────┘
        │                 │
        └────────┬────────┘
                 │
        ┌────────▼────────┐
        │    RabbitMQ     │
        └────────┬────────┘
                 │
        ┌────────┴────────┐
        │                 │
┌───────▼──────┐  ┌───────▼──────┐  ┌───────────┐
│  Worker 1    │  │  Worker 2    │  │  Worker N │  ◄─── Add more workers
└───────┬──────┘  └───────┬──────┘  └───────┬───┘
        │                 │                 │
        └────────┬────────┴─────────────────┘
                 │
        ┌────────▼────────┐
        │   PostgreSQL    │  ◄─── Read replicas for queries
        │   (Primary +    │
        │    Replicas)    │
        └─────────────────┘
```

---

## Summary

This architecture provides:
- ✅ **High availability** through async processing
- ✅ **Scalability** via horizontal worker scaling
- ✅ **Reliability** with RabbitMQ persistence
- ✅ **Data integrity** with ACID transactions
- ✅ **Concurrency safety** with pessimistic locking
- ✅ **Deadlock prevention** with sorted locking
- ✅ **Idempotency** with Redis (ready to enable)
- ✅ **Clean architecture** with modular monolith
