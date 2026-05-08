<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\CompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Company entity for multi-tenancy support
 *
 * Represents different types of companies in factoring business:
 * - Factoring company (the platform owner)
 * - Suppliers (clients who sell their receivables)
 * - Debtors (buyers who owe money)
 */
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'companies')]
#[ORM\Index(columns: ['inn'], name: 'idx_companies_inn')]
#[ORM\Index(columns: ['type'], name: 'idx_companies_type')]
#[ORM\Index(columns: ['status'], name: 'idx_companies_status')]
class Company
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 12, unique: true)]
    private string $inn;

    #[ORM\Column(type: Types::STRING, length: 9, nullable: true)]
    private ?string $kpp = null;

    #[ORM\Column(type: Types::STRING, length: 15, nullable: true)]
    private ?string $ogrn = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status;

    #[ORM\Column(type: Types::TEXT)]
    private string $legalAddress;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $postalAddress = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $bankAccount = null;

    #[ORM\Column(type: Types::STRING, length: 9, nullable: true)]
    private ?string $bankBik = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $correspondentAccount = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Uuid $id,
        string $name,
        string $inn,
        string $type,
        string $legalAddress
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->inn = $inn;
        $this->type = $type;
        $this->status = CompanyStatus::ACTIVE->value;
        $this->legalAddress = $legalAddress;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getInn(): string
    {
        return $this->inn;
    }

    public function setInn(string $inn): self
    {
        $this->inn = $inn;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getKpp(): ?string
    {
        return $this->kpp;
    }

    public function setKpp(?string $kpp): self
    {
        $this->kpp = $kpp;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getOgrn(): ?string
    {
        return $this->ogrn;
    }

    public function setOgrn(?string $ogrn): self
    {
        $this->ogrn = $ogrn;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeEnum(): CompanyType
    {
        return CompanyType::from($this->type);
    }

    public function setType(CompanyType $type): self
    {
        $this->type = $type->value;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusEnum(): CompanyStatus
    {
        return CompanyStatus::from($this->status);
    }

    public function activate(): self
    {
        $this->status = CompanyStatus::ACTIVE->value;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function suspend(): self
    {
        $this->status = CompanyStatus::SUSPENDED->value;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function block(): self
    {
        $this->status = CompanyStatus::BLOCKED->value;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === CompanyStatus::ACTIVE->value;
    }

    public function getLegalAddress(): string
    {
        return $this->legalAddress;
    }

    public function setLegalAddress(string $legalAddress): self
    {
        $this->legalAddress = $legalAddress;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPostalAddress(): ?string
    {
        return $this->postalAddress;
    }

    public function setPostalAddress(?string $postalAddress): self
    {
        $this->postalAddress = $postalAddress;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getBankAccount(): ?string
    {
        return $this->bankAccount;
    }

    public function setBankAccount(?string $bankAccount): self
    {
        $this->bankAccount = $bankAccount;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getBankBik(): ?string
    {
        return $this->bankBik;
    }

    public function setBankBik(?string $bankBik): self
    {
        $this->bankBik = $bankBik;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCorrespondentAccount(): ?string
    {
        return $this->correspondentAccount;
    }

    public function setCorrespondentAccount(?string $correspondentAccount): self
    {
        $this->correspondentAccount = $correspondentAccount;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

/**
 * Company type enum
 */
enum CompanyType: string
{
    case FACTOR = 'factor';
    case SUPPLIER = 'supplier';
    case DEBTOR = 'debtor';
}

/**
 * Company status enum
 */
enum CompanyStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';
}
