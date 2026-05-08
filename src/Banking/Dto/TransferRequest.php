<?php

declare(strict_types=1);

namespace App\Banking\Dto;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for transfer request validation
 * Provides type-safe request data with automatic validation
 */
class TransferRequest
{
    #[Assert\NotBlank(message: 'Field "fromId" is required')]
    #[Assert\Uuid(message: 'Field "fromId" must be a valid UUID')]
    public string $fromId;

    #[Assert\NotBlank(message: 'Field "toId" is required')]
    #[Assert\Uuid(message: 'Field "toId" must be a valid UUID')]
    public string $toId;

    #[Assert\NotBlank(message: 'Field "amount" is required')]
    #[Assert\Positive(message: 'Field "amount" must be a positive integer')]
    #[Assert\Type(type: 'integer', message: 'Field "amount" must be an integer')]
    public int $amount;

    #[Assert\Uuid(message: 'Field "idempotencyKey" must be a valid UUID')]
    public ?string $idempotencyKey = null;

    /**
     * Get fromId as Uuid object
     */
    public function getFromId(): Uuid
    {
        return Uuid::fromString($this->fromId);
    }

    /**
     * Get toId as Uuid object
     */
    public function getToId(): Uuid
    {
        return Uuid::fromString($this->toId);
    }

    /**
     * Get or generate idempotency key
     */
    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey ?? Uuid::v4()->toRfc4122();
    }

    /**
     * Custom validation: ensure fromId and toId are different
     */
    #[Assert\IsTrue(message: 'Cannot transfer to the same account')]
    public function isDifferentAccounts(): bool
    {
        return $this->fromId !== $this->toId;
    }
}
