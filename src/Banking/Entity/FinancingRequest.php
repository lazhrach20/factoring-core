<?php

declare(strict_types=1);

namespace App\Banking\Entity;

use App\Banking\Repository\FinancingRequestRepository;
use App\Identity\Entity\Company;
use App\Identity\Entity\User;
use App\Shared\ValueObject\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Заявка на финансирование (упрощенная MVP версия)
 *
 * Бизнес-процесс:
 * 1. Фактор создает заявку для поставщика (указывает дебитора)
 * 2. Дебитор видит заявку и может одобрить
 * 3. После одобрения деньги автоматически переводятся со счета фактора на счет поставщика
 */
#[ORM\Entity(repositoryClass: FinancingRequestRepository::class)]
#[ORM\Table(name: 'financing_requests')]
#[ORM\Index(name: 'idx_financing_requests_supplier', columns: ['supplier_company_id'])]
#[ORM\Index(name: 'idx_financing_requests_debtor', columns: ['debtor_company_id'])]
#[ORM\Index(name: 'idx_financing_requests_status', columns: ['status'])]
#[ORM\Index(name: 'idx_financing_requests_created_at', columns: ['created_at'])]
class FinancingRequest
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    /**
     * Сумма финансирования в минорных единицах (копейки)
     */
    #[ORM\Column(type: Types::BIGINT)]
    private string $amountMinorUnits;

    #[ORM\Column(length: 3)]
    private string $currency = 'RUB';

    /**
     * Поставщик (получит деньги)
     */
    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'supplier_company_id', referencedColumnName: 'id', nullable: false)]
    private Company $supplierCompany;

    /**
     * Дебитор (должен одобрить заявку)
     */
    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'debtor_company_id', referencedColumnName: 'id', nullable: false)]
    private Company $debtorCompany;

    /**
     * Счет фактора (откуда будут списаны деньги)
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'factor_account_id', referencedColumnName: 'id', nullable: false)]
    private Account $factorAccount;

    /**
     * Счет поставщика (куда будут зачислены деньги)
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'supplier_account_id', referencedColumnName: 'id', nullable: false)]
    private Account $supplierAccount;

    #[ORM\Column(length: 20, enumType: FinancingRequestStatus::class)]
    private FinancingRequestStatus $status = FinancingRequestStatus::PENDING;

    /**
     * Кто создал заявку (обычно пользователь фактора)
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    /**
     * Кто одобрил заявку (пользователь дебитора)
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'approved_by_id', referencedColumnName: 'id', nullable: true)]
    private ?User $approvedBy = null;

    /**
     * Транзакция финансирования (после одобрения)
     */
    #[ORM\OneToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(name: 'transaction_id', referencedColumnName: 'id', nullable: true)]
    private ?Transaction $transaction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $fundedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Money $amount,
        Company $supplierCompany,
        Company $debtorCompany,
        Account $factorAccount,
        Account $supplierAccount,
        User $createdBy,
        ?string $description = null
    ) {
        $this->amountMinorUnits = (string) $amount->getMinorUnits();
        $this->currency = $amount->getCurrency()->value;
        $this->supplierCompany = $supplierCompany;
        $this->debtorCompany = $debtorCompany;
        $this->factorAccount = $factorAccount;
        $this->supplierAccount = $supplierAccount;
        $this->createdBy = $createdBy;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAmount(): Money
    {
        return Money::fromMinorUnits(
            (int) $this->amountMinorUnits,
            \App\Shared\ValueObject\Currency::from($this->currency)
        );
    }

    public function getAmountMinorUnits(): int
    {
        return (int) $this->amountMinorUnits;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSupplierCompany(): Company
    {
        return $this->supplierCompany;
    }

    public function getDebtorCompany(): Company
    {
        return $this->debtorCompany;
    }

    public function getFactorAccount(): Account
    {
        return $this->factorAccount;
    }

    public function getSupplierAccount(): Account
    {
        return $this->supplierAccount;
    }

    public function getStatus(): FinancingRequestStatus
    {
        return $this->status;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getFundedAt(): ?\DateTimeImmutable
    {
        return $this->fundedAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Одобрить заявку (вызывается пользователем дебитора)
     */
    public function approve(User $approvedBy): void
    {
        if (!$this->status->canApprove()) {
            throw new \DomainException("Cannot approve financing request in status: {$this->status->value}");
        }

        $this->status = FinancingRequestStatus::APPROVED;
        $this->approvedBy = $approvedBy;
        $this->approvedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Отклонить заявку
     */
    public function reject(User $rejectedBy, string $reason): void
    {
        if ($this->status->isFinal()) {
            throw new \DomainException("Cannot reject financing request in final status: {$this->status->value}");
        }

        $this->status = FinancingRequestStatus::REJECTED;
        $this->rejectionReason = $reason;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Пометить как профинансированную (после создания транзакции)
     */
    public function markAsFunded(Transaction $transaction): void
    {
        if (!$this->status->canFund()) {
            throw new \DomainException("Cannot fund financing request in status: {$this->status->value}");
        }

        $this->status = FinancingRequestStatus::FUNDED;
        $this->transaction = $transaction;
        $this->fundedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Отменить заявку
     */
    public function cancel(): void
    {
        if ($this->status->isFinal()) {
            throw new \DomainException("Cannot cancel financing request in final status: {$this->status->value}");
        }

        $this->status = FinancingRequestStatus::CANCELLED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Может ли пользователь видеть эту заявку
     */
    public function canBeViewedBy(User $user): bool
    {
        $userCompanyId = $user->getCompany()->getId();

        return $userCompanyId->equals($this->supplierCompany->getId())
            || $userCompanyId->equals($this->debtorCompany->getId())
            || $userCompanyId->equals($this->factorAccount->getCompany()->getId());
    }

    /**
     * Может ли пользователь одобрить заявку
     * Только фактор может одобрять заявки
     */
    public function canBeApprovedBy(User $user): bool
    {
        // Только пользователи компании-фактора могут одобрять заявки
        if ($user->getCompany()->getType() !== 'factor') {
            return false;
        }

        // Проверяем, что заявка в подходящем статусе для одобрения
        return $this->status->canApprove();
    }
}
