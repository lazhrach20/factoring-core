<?php

declare(strict_types=1);

namespace App\Banking\Message;

/**
 * Асинхронное сообщение для обработки депозита
 */
final class ProcessDepositMessage
{
    public function __construct(
        private readonly string $transactionId,
        private readonly string $idempotencyKey
    ) {
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }
}
