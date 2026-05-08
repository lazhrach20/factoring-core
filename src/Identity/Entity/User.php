<?php

declare(strict_types=1);

namespace App\Identity\Entity;

use App\Identity\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * User entity for authentication and authorization
 *
 * This is a minimal version without roles (will be added in later stages)
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_users_email')]
#[ORM\Index(columns: ['company_id'], name: 'idx_users_company_id')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $firstName;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $lastName;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $companyId;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    private Company $company;

    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_roles')]
    private Collection $roles;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isBlocked = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $blockedReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $securityVersion = 1;

    public function __construct(
        Uuid $id,
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        Company $company
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->company = $company;
        $this->companyId = $company->getId();
        $this->roles = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCompanyId(): Uuid
    {
        return $this->companyId;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): self
    {
        $this->company = $company;
        $this->companyId = $company->getId();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function block(string $reason): self
    {
        $this->isBlocked = true;
        $this->blockedReason = $reason;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function unblock(): self
    {
        $this->isBlocked = false;
        $this->blockedReason = null;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getBlockedReason(): ?string
    {
        return $this->blockedReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function updateLastLogin(string $ip): self
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $this->lastLoginIp = $ip;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function getSecurityVersion(): int
    {
        return $this->securityVersion;
    }

    public function incrementSecurityVersion(): self
    {
        $this->securityVersion++;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    // ==================== UserInterface Methods ====================

    /**
     * A visual identifier that represents this user.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Get user roles including assigned roles from database
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER']; // Everyone gets ROLE_USER

        foreach ($this->roles as $role) {
            $roles[] = $role->getName();
        }

        return array_unique($roles);
    }

    /**
     * Get Role entities
     * @return Collection<int, Role>
     */
    public function getUserRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }
        return $this;
    }

    public function removeRole(Role $role): self
    {
        $this->roles->removeElement($role);
        return $this;
    }

    public function hasRole(string $roleName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->getName() === $roleName) {
                return true;
            }
        }
        return false;
    }

    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->hasPermissionByName($permissionName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * This method can be removed in Symfony 6.0 - is not needed for apps that do not check user passwords.
     */
    public function eraseCredentials(): void
    {
    }
}
