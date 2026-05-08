<?php

declare(strict_types=1);

namespace App\Tests\Unit\Banking\Entity;

use App\Banking\Entity\Account;
use App\Banking\Entity\Transaction;
use App\Banking\Entity\TransactionStatus;
use App\Banking\Entity\TransactionType;
use App\Identity\Entity\Company;
use App\Identity\Entity\User;
use App\Shared\ValueObject\Currency;
use App\Shared\ValueObject\Money;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class TransactionTest extends TestCase
{
    private Account $debitAccount;
    private Account $creditAccount;
    private User $user;
    private Money $amount;

    protected function setUp(): void
    {
        // Mock objects для тестирования
        $company = $this->createMock(Company::class);

        $this->user = $this->createMock(User::class);

        $this->debitAccount = new Account(
            'Debit Account',
            Uuid::v4(),
            $company,
            Currency::RUB
        );

        $this->creditAccount = new Account(
            'Credit Account',
            Uuid::v4(),
            $company,
            Currency::RUB
        );

        $this->amount = Money::RUB(1000);
    }

    public function testCreateTransaction(): void
    {
        $idempotencyKey = Uuid::v4()->toRfc4122();

        $transaction = Transaction::create(
            id: Uuid::v4(),
            debitAccount: $this->debitAccount,
            creditAccount: $this->creditAccount,
            amount: $this->amount,
            type: TransactionType::TRANSFER,
            idempotencyKey: $idempotencyKey,
            initiatedBy: $this->user,
            description: 'Test transaction'
        );

        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($this->debitAccount, $transaction->getDebitAccount());
        $this->assertEquals($this->creditAccount, $transaction->getCreditAccount());
        $this->assertTrue($this->amount->equals($transaction->getAmount()));
        $this->assertEquals(TransactionType::TRANSFER, $transaction->getType());
        $this->assertEquals(TransactionStatus::PENDING, $transaction->getStatus());
        $this->assertEquals($idempotencyKey, $transaction->getIdempotencyKey());
        $this->assertEquals('Test transaction', $transaction->getDescription());
    }

    public function testMarkAsCompleted(): void
    {
        $transaction = $this->createPendingTransaction();

        $transaction->markAsCompleted();

        $this->assertTrue($transaction->isCompleted());
        $this->assertEquals(TransactionStatus::COMPLETED, $transaction->getStatus());
    }

    public function testMarkAsCompletedOnFinalizedTransactionThrowsException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot change status of finalized transaction');

        $transaction = $this->createPendingTransaction();
        $transaction->markAsCompleted();

        // Попытка изменить уже завершенную транзакцию
        $transaction->markAsCompleted();
    }

    public function testMarkAsFailed(): void
    {
        $transaction = $this->createPendingTransaction();

        $transaction->markAsFailed('Network timeout');

        $this->assertTrue($transaction->isFailed());
        $this->assertEquals(TransactionStatus::FAILED, $transaction->getStatus());

        $metadata = $transaction->getMetadata();
        $this->assertArrayHasKey('failure_reason', $metadata);
        $this->assertEquals('Network timeout', $metadata['failure_reason']);
    }

    public function testReverse(): void
    {
        $transaction = $this->createPendingTransaction();
        $transaction->markAsCompleted();

        $transaction->reverse('Refund requested');

        $this->assertTrue($transaction->isReversed());
        $this->assertEquals(TransactionStatus::REVERSED, $transaction->getStatus());

        $metadata = $transaction->getMetadata();
        $this->assertArrayHasKey('reversal_reason', $metadata);
        $this->assertEquals('Refund requested', $metadata['reversal_reason']);
    }

    public function testReverseOnNonCompletedTransactionThrowsException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Can only reverse completed transactions');

        $transaction = $this->createPendingTransaction();

        $transaction->reverse('Test');
    }

    public function testGetAmountReturnsMoneyObject(): void
    {
        $transaction = $this->createPendingTransaction();

        $amount = $transaction->getAmount();

        $this->assertInstanceOf(Money::class, $amount);
        $this->assertTrue($this->amount->equals($amount));
    }

    public function testTransactionStatusChecks(): void
    {
        $transaction = $this->createPendingTransaction();

        $this->assertFalse($transaction->isCompleted());
        $this->assertFalse($transaction->isFailed());
        $this->assertFalse($transaction->isReversed());

        $transaction->markAsCompleted();
        $this->assertTrue($transaction->isCompleted());

        $transaction2 = $this->createPendingTransaction();
        $transaction2->markAsFailed('Test');
        $this->assertTrue($transaction2->isFailed());
    }

    public function testMetadata(): void
    {
        $metadata = [
            'request_id' => '123',
            'ip_address' => '127.0.0.1',
        ];

        $transaction = Transaction::create(
            id: Uuid::v4(),
            debitAccount: $this->debitAccount,
            creditAccount: $this->creditAccount,
            amount: $this->amount,
            type: TransactionType::TRANSFER,
            idempotencyKey: Uuid::v4()->toRfc4122(),
            initiatedBy: $this->user,
            metadata: $metadata
        );

        $this->assertEquals($metadata, $transaction->getMetadata());
    }

    public function testRelatedEntity(): void
    {
        $relatedId = Uuid::v4();

        $transaction = Transaction::create(
            id: Uuid::v4(),
            debitAccount: $this->debitAccount,
            creditAccount: $this->creditAccount,
            amount: $this->amount,
            type: TransactionType::FINANCING,
            idempotencyKey: Uuid::v4()->toRfc4122(),
            initiatedBy: $this->user,
            relatedEntityId: $relatedId,
            relatedEntityType: 'FinancingRequest'
        );

        $this->assertEquals($relatedId, $transaction->getRelatedEntityId());
        $this->assertEquals('FinancingRequest', $transaction->getRelatedEntityType());
    }

    private function createPendingTransaction(): Transaction
    {
        return Transaction::create(
            id: Uuid::v4(),
            debitAccount: $this->debitAccount,
            creditAccount: $this->creditAccount,
            amount: $this->amount,
            type: TransactionType::TRANSFER,
            idempotencyKey: Uuid::v4()->toRfc4122(),
            initiatedBy: $this->user
        );
    }
}
