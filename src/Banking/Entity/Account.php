<?php

declare(strict_types=1);

namespace App\Banking\Entity;

use App\Banking\Repository\AccountRepository;
use App\Identity\Entity\Company;
use App\Shared\ValueObject\Currency;
use App\Shared\ValueObject\Money;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'banking_accounts')]
#[ORM\Index(name: 'idx_banking_accounts_company_id', columns: ['company_id'])]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    /**
     * Кэшированный баланс в минорных единицах (копейки/центы)
     * Реальный баланс рассчитывается из транзакций
     */
    #[ORM\Column(name: 'balance_cached', type: Types::BIGINT, options: ['default' => 0])]
    private string $balanceCached = '0';

    #[ORM\Column(length: 3)]
    private string $currency = 'RUB';

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $ownerId;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    private Company $company;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Version]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, Uuid $ownerId, Company $company, Currency $currency = Currency::RUB)
    {
        $this->name = $name;
        $this->ownerId = $ownerId;
        $this->company = $company;
        $this->currency = $currency->value;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Creates a system account with a deterministic UUID.
     * Used for external deposit/withdrawal operations.
     */
    public static function createWithId(Uuid $id, string $name, Uuid $ownerId, Company $company, Currency $currency): self
    {
        $account = new self($name, $ownerId, $company, $currency);
        $account->id = $id;

        return $account;
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCurrency(): Currency
    {
        return Currency::from($this->currency);
    }

    public function getOwnerId(): Uuid
    {
        return $this->ownerId;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Получить кэшированный баланс как Money VO
     */
    public function getBalance(): Money
    {
        return Money::fromMinorUnits(
            (int) $this->balanceCached,
            Currency::from($this->currency)
        );
    }

    /**
     * Получить баланс в минорных единицах (int)
     */
    public function getBalanceMinorUnits(): int
    {
        return (int) $this->balanceCached;
    }

    /**
     * Увеличить кэшированный баланс
     *
     * Вызывается TransactionService после подтверждения кредитовой транзакции
     */
    public function increaseBalance(Money $amount): void
    {
        // Проверка валюты
        if ($amount->getCurrency() !== Currency::from($this->currency)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Currency mismatch: account currency is %s, but trying to increase by %s',
                    $this->currency,
                    $amount->getCurrency()->value
                )
            );
        }

        if ($amount->isNegative()) {
            throw new \InvalidArgumentException('Cannot increase balance by negative amount');
        }

        $currentBalance = (int) $this->balanceCached;
        $newBalance = $currentBalance + $amount->getMinorUnits();

        // Проверка переполнения
        if ($newBalance < $currentBalance) {
            throw new \OverflowException('Balance overflow detected');
        }

        $this->balanceCached = (string) $newBalance;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Уменьшить кэшированный баланс
     *
     * Вызывается TransactionService после подтверждения дебетовой транзакции
     */
    public function decreaseBalance(Money $amount): void
    {
        // Проверка валюты
        if ($amount->getCurrency() !== Currency::from($this->currency)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Currency mismatch: account currency is %s, but trying to decrease by %s',
                    $this->currency,
                    $amount->getCurrency()->value
                )
            );
        }

        if ($amount->isNegative()) {
            throw new \InvalidArgumentException('Cannot decrease balance by negative amount');
        }

        $currentBalance = (int) $this->balanceCached;
        $requestedAmount = $amount->getMinorUnits();

        if ($currentBalance < $requestedAmount) {
            throw new \DomainException(
                sprintf(
                    'Insufficient funds. Current balance: %s, Requested: %s',
                    Money::fromMinorUnits($currentBalance, Currency::from($this->currency))->format(),
                    $amount->format()
                )
            );
        }

        $this->balanceCached = (string) ($currentBalance - $requestedAmount);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Установить кэшированный баланс
     *
     * Используется для синхронизации кэша с реальным балансом из транзакций
     */
    public function syncBalance(Money $calculatedBalance): void
    {
        if ($calculatedBalance->getCurrency() !== Currency::from($this->currency)) {
            throw new \InvalidArgumentException('Currency mismatch during balance sync');
        }

        $this->balanceCached = (string) $calculatedBalance->getMinorUnits();
        $this->updatedAt = new \DateTimeImmutable();
    }
}

