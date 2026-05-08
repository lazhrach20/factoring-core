<?php

declare(strict_types=1);

namespace App\Banking\Service;

use App\Banking\Entity\Transaction;
use Psr\Log\LoggerInterface;

/**
 * Bank API integration service.
 * MVP stub — always returns success. Replace with real bank API calls before production.
 */
final class BankIntegrationService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Processes a deposit via the bank API.
     *
     * @param Transaction $transaction
     * @param string $idempotencyKey Idempotency key to prevent duplicate processing
     * @return array{success: bool, bankReference?: string, error?: string}
     */
    public function processDeposit(Transaction $transaction, string $idempotencyKey): array
    {
        $this->logger->info('[BankAPI] Processing deposit', [
            'transactionId' => $transaction->getId(),
            'amount' => $transaction->getAmount()->toFloat(),
            'currency' => $transaction->getCurrency()->value,
            'idempotencyKey' => $idempotencyKey,
        ]);

        // TODO: Integrate with real bank API

        // MVP stub: always returns success
        return [
            'success' => true,
            'bankReference' => 'MOCK-' . strtoupper(substr($idempotencyKey, 0, 8)),
        ];
    }

    /**
     * Processes a transfer via the bank API.
     */
    public function processTransfer(Transaction $transaction, string $idempotencyKey): array
    {
        $this->logger->info('[BankAPI] Processing transfer', [
            'transactionId' => $transaction->getId(),
            'amount' => $transaction->getAmount()->toFloat(),
            'from' => $transaction->getDebitAccount()?->getAccountNumber(),
            'to' => $transaction->getCreditAccount()->getAccountNumber(),
            'idempotencyKey' => $idempotencyKey,
        ]);

        // TODO: Integrate with real bank API

        // MVP stub: always returns success
        return [
            'success' => true,
            'bankReference' => 'MOCK-' . strtoupper(substr($idempotencyKey, 0, 8)),
        ];
    }

    /**
     * Processes a withdrawal via the bank API.
     */
    public function processWithdrawal(Transaction $transaction, string $idempotencyKey): array
    {
        $this->logger->info('[BankAPI] Processing withdrawal', [
            'transactionId' => $transaction->getId(),
            'amount' => $transaction->getAmount()->toFloat(),
            'account' => $transaction->getDebitAccount()?->getAccountNumber(),
            'idempotencyKey' => $idempotencyKey,
        ]);

        // TODO: Integrate with real bank API

        // MVP stub: always returns success
        return [
            'success' => true,
            'bankReference' => 'MOCK-' . strtoupper(substr($idempotencyKey, 0, 8)),
        ];
    }
}
