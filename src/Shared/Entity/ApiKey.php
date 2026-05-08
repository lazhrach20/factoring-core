<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use App\Identity\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'api_keys')]
#[ORM\Index(columns: ['key_hash'], name: 'idx_api_key_hash')]
#[ORM\Index(columns: ['user_id'], name: 'idx_api_key_user')]
#[ORM\Index(columns: ['is_active'], name: 'idx_api_key_active')]
class ApiKey
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $keyHash;

    #[ORM\Column(type: Types::STRING, length: 16)]
    private string $keyPrefix; // sk_live_, sk_test_

    #[ORM\Column(type: Types::JSON)]
    private array $scopes = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ipWhitelist = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $rateLimit = 1000; // requests per hour

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(
        User $user,
        string $name,
        string $keyHash,
        string $keyPrefix,
        array $scopes = [],
        ?array $ipWhitelist = null,
        int $rateLimit = 1000
    ) {
        $this->id = Uuid::v4();
        $this->user = $user;
        $this->name = $name;
        $this->keyHash = $keyHash;
        $this->keyPrefix = $keyPrefix;
        $this->scopes = $scopes;
        $this->ipWhitelist = $ipWhitelist;
        $this->rateLimit = $rateLimit;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    public function getKeyPrefix(): string
    {
        return $this->keyPrefix;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function getIpWhitelist(): ?array
    {
        return $this->ipWhitelist;
    }

    public function setIpWhitelist(?array $ipWhitelist): self
    {
        $this->ipWhitelist = $ipWhitelist;
        return $this;
    }

    public function isIpAllowed(string $ip): bool
    {
        // If no whitelist, allow all IPs
        if ($this->ipWhitelist === null || empty($this->ipWhitelist)) {
            return true;
        }

        return in_array($ip, $this->ipWhitelist, true);
    }

    public function getRateLimit(): int
    {
        return $this->rateLimit;
    }

    public function setRateLimit(int $rateLimit): self
    {
        $this->rateLimit = $rateLimit;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): self
    {
        $this->isActive = true;
        $this->revokedAt = null;
        return $this;
    }

    public function revoke(): self
    {
        $this->isActive = false;
        $this->revokedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isRevoked(): bool
    {
        return !$this->isActive || $this->revokedAt !== null;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markAsUsed(): self
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable();
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isValid(): bool
    {
        return $this->isActive && !$this->isExpired() && !$this->isRevoked();
    }
}
