<?php

declare(strict_types=1);

namespace App\Banking\Entity;

use App\Banking\Repository\TransactionRepository;
use App\Identity\Entity\User;
use App\Shared\ValueObject\Currency;
use App\Shared\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Transaction - Неизменяемая запись о денежной операции (Immutable Ledger)
 *
 * Ключевые принципы:
 * - Транзакция никогда не удаляется и не изменяется
 * - Баланс счета - это сумма всех транзакций
 * - Каждая транзакция имеет дебет (откуда) и кредит (куда)
 * - Idempotency key предотвращает дублирование
 */
#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(columns: ['debit_account_id'], name: 'idx_debit_account')]
#[ORM\Index(columns: ['credit_account_id'], name: 'idx_credit_account')]
#[ORM\Index(columns: ['idempotency_key'], name: 'idx_idempotency_key')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['executed_at'], name: 'idx_executed_at')]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    /**
     * Счет, с которого списываются средства (дебет)
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'debit_account_id', nullable: false, onDelete: 'RESTRICT')]
    private Account $debitAccount;

    /**
     * Счет, на который зачисляются средства (кредит)
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'credit_account_id', nullable: false, onDelete: 'RESTRICT')]
    private Account $creditAccount;

    /**
     * Сумма в минимальных единицах (копейки/центы)
     */
    #[ORM\Column(type: Types::BIGINT)]
    private int $amountMinorUnits;

    /**
     * Валюта транзакции
     */
    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    /**
     * Тип транзакции
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $type;

    /**
     * Статус транзакции
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status;

    /**
     * Ключ идемпотентности для предотвращения дублирования
     */
    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $idempotencyKey;

    /**
     * Пользователь, инициировавший транзакцию
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $initiatedBy;

    /**
     * Описание/комментарий к транзакции
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    /**
     * Дополнительные метаданные (JSON)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata;

    /**
     * Связанная сущность (FinancingRequest, Invoice, etc.)
     */
    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $relatedEntityId;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $relatedEntityType;

    /**
     * Дата выполнения транзакции
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $executedAt;

    /**
     * Дата создания записи
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /**
     * Дата обработки транзакции (когда worker завершил)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    /**
     * Причина ошибки (если status = FAILED)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $failureReason = null;

    /**
     * Приватный конструктор - создание только через фабричный метод
     */
    private function __construct()
    {
    }

    /**
     * Фабричный метод для создания транзакции
     */
    public static function create(
        Uuid $id,
        Account $debitAccount,
        Account $creditAccount,
        Money $amount,
        TransactionType $type,
        string $idempotencyKey,
        User $initiatedBy,
        ?string $description = null,
        ?array $metadata = null,
        ?Uuid $relatedEntityId = null,
        ?string $relatedEntityType = null
    ): self {
        $transaction = new self();

        $transaction->id = $id;
        $transaction->debitAccount = $debitAccount;
        $transaction->creditAccount = $creditAccount;
        $transaction->amountMinorUnits = $amount->getMinorUnits();
        $transaction->currency = $amount->getCurrency()->value;
        $transaction->type = $type->value;
        $transaction->status = TransactionStatus::PENDING->value;
        $transaction->idempotencyKey = $idempotencyKey;
        $transaction->initiatedBy = $initiatedBy;
        $transaction->description = $description;
        $transaction->metadata = $metadata;
        $transaction->relatedEntityId = $relatedEntityId;
        $transaction->relatedEntityType = $relatedEntityType;
        $transaction->executedAt = new DateTimeImmutable();
        $transaction->createdAt = new DateTimeImmutable();

        return $transaction;
    }

    /**
     * Отметить транзакцию как завершенную
     */
    public function markAsCompleted(): void
    {
        $status = TransactionStatus::from($this->status);

        if ($status->isFinal()) {
            throw new \LogicException('Cannot change status of finalized transaction');
        }

        $this->status = TransactionStatus::COMPLETED->value;
    }

    /**
     * Отметить транзакцию как неудачную
     */
    public function markAsFailed(string $reason): void
    {
        $status = TransactionStatus::from($this->status);

        if ($status->isFinal()) {
            throw new \LogicException('Cannot change status of finalized transaction');
        }

        $this->status = TransactionStatus::FAILED->value;
        $this->metadata = array_merge($this->metadata ?? [], [
            'failure_reason' => $reason,
            'failed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Отменить транзакцию (реверс)
     */
    public function reverse(string $reason): void
    {
        if ($this->status !== TransactionStatus::COMPLETED->value) {
            throw new \LogicException('Can only reverse completed transactions');
        }

        $this->status = TransactionStatus::REVERSED->value;
        $this->metadata = array_merge($this->metadata ?? [], [
            'reversal_reason' => $reason,
            'reversed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    // Геттеры

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDebitAccount(): Account
    {
        return $this->debitAccount;
    }

    public function getCreditAccount(): Account
    {
        return $this->creditAccount;
    }

    public function getAmount(): Money
    {
        return Money::fromMinorUnits($this->amountMinorUnits, Currency::from($this->currency));
    }

    public function getAmountMinorUnits(): int
    {
        return $this->amountMinorUnits;
    }

    public function getCurrency(): Currency
    {
        return Currency::from($this->currency);
    }

    public function getType(): TransactionType
    {
        return TransactionType::from($this->type);
    }

    public function getStatus(): TransactionStatus
    {
        return TransactionStatus::from($this->status);
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getInitiatedBy(): User
    {
        return $this->initiatedBy;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getRelatedEntityId(): ?Uuid
    {
        return $this->relatedEntityId;
    }

    public function getRelatedEntityType(): ?string
    {
        return $this->relatedEntityType;
    }

    public function getExecutedAt(): DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCompleted(): bool
    {
        return $this->status === TransactionStatus::COMPLETED->value;
    }

    public function isFailed(): bool
    {
        return $this->status === TransactionStatus::FAILED->value;
    }

    public function isReversed(): bool
    {
        return $this->status === TransactionStatus::REVERSED->value;
    }

    public function setProcessedAt(DateTimeImmutable $processedAt): void
    {
        $this->processedAt = $processedAt;
    }

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setFailureReason(?string $reason): void
    {
        $this->failureReason = $reason;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setStatus(TransactionStatus $status): void
    {
        $this->status = $status->value;
    }
}
