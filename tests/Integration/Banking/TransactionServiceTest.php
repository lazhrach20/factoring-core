<?php

declare(strict_types=1);

namespace App\Tests\Integration\Banking;

use App\Banking\Entity\Account;
use App\Banking\Entity\Transaction;
use App\Banking\Entity\TransactionStatus;
use App\Banking\Entity\TransactionType;
use App\Banking\Repository\TransactionRepository;
use App\Banking\Service\TransactionService;
use App\Identity\Entity\Company;
use App\Identity\Entity\User;
use App\Shared\Exception\InsufficientFundsException;
use App\Shared\ValueObject\Currency;
use App\Shared\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class TransactionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private TransactionService $transactionService;
    private TransactionRepository $transactionRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->transactionService = self::getContainer()->get(TransactionService::class);
        $this->transactionRepository = self::getContainer()->get(TransactionRepository::class);

        // Очистка БД перед каждым тестом
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Откат изменений после теста
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testTransferMoneyBetweenAccounts(): void
    {
        // Arrange
        $company = $this->createCompany();
        $user = $this->createUser();

        $fromAccount = $this->createAccount('From Account', $company);
        $toAccount = $this->createAccount('To Account', $company);

        // Установим начальный баланс
        $fromAccount->syncBalance(Money::RUB(10000));

        $this->entityManager->persist($company);
        $this->entityManager->persist($user);
        $this->entityManager->persist($fromAccount);
        $this->entityManager->persist($toAccount);
        $this->entityManager->flush();

        $amount = Money::RUB(1000);
        $idempotencyKey = Uuid::v4()->toRfc4122();

        // Act
        $transaction = $this->transactionService->transfer(
            from: $fromAccount,
            to: $toAccount,
            amount: $amount,
            type: TransactionType::TRANSFER,
            idempotencyKey: $idempotencyKey,
            initiatedBy: $user,
            description: 'Test transfer'
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertTrue($transaction->isCompleted());
        $this->assertEquals(TransactionStatus::COMPLETED, $transaction->getStatus());

        // Проверим балансы
        $this->assertEquals(9000.0, $fromAccount->getBalance()->toFloat());
        $this->assertEquals(1000.0, $toAccount->getBalance()->toFloat());

        // Проверим что транзакция сохранена
        $savedTransaction = $this->transactionRepository->findByIdempotencyKey($idempotencyKey);
        $this->assertNotNull($savedTransaction);
        $this->assertEquals($transaction->getId(), $savedTransaction->getId());
    }

    public function testIdempotencyPreventsDoubleDebit(): void
    {
        // Arrange
        $company = $this->createCompany();
        $user = $this->createUser();

        $fromAccount = $this->createAccount('From Account', $company);
        $toAccount = $this->createAccount('To Account', $company);

        $fromAccount->syncBalance(Money::RUB(10000));

        $this->entityManager->persist($company);
        $this->entityManager->persist($user);
        $this->entityManager->persist($fromAccount);
        $this->entityManager->persist($toAccount);
        $this->entityManager->flush();

        $amount = Money::RUB(1000);
        $idempotencyKey = Uuid::v4()->toRfc4122();

        // Act - первый вызов
        $transaction1 = $this->transactionService->transfer(
            from: $fromAccount,
            to: $toAccount,
            amount: $amount,
            type: TransactionType::TRANSFER,
            idempotencyKey: $idempotencyKey,
            initiatedBy: $user
        );

        // Act - второй вызов с тем же ключом (симуляция retry)
        $transaction2 = $this->transactionService->transfer(
            from: $fromAccount,
            to: $toAccount,
            amount: $amount,
            type: TransactionType::TRANSFER,
            idempotencyKey: $idempotencyKey,
            initiatedBy: $user
        );

        // Assert
        $this->assertEquals($transaction1->getId(), $transaction2->getId());

        // Проверим что деньги списались только один раз
        $this->assertEquals(9000.0, $fromAccount->getBalance()->toFloat());
        $this->assertEquals(1000.0, $toAccount->getBalance()->toFloat());
    }

    public function testTransferWithInsufficientFundsThrowsException(): void
    {
        // Arrange
        $company = $this->createCompany();
        $user = $this->createUser();

        $fromAccount = $this->createAccount('From Account', $company);
        $toAccount = $this->createAccount('To Account', $company);

        $fromAccount->syncBalance(Money::RUB(500)); // Недостаточно средств

        $this->entityManager->persist($company);
        $this->entityManager->persist($user);
        $this->entityManager->persist($fromAccount);
        $this->entityManager->persist($toAccount);
        $this->entityManager->flush();

        $amount = Money::RUB(1000); // Пытаемся перевести больше

        // Expect & Act
        $this->expectException(InsufficientFundsException::class);

        $this->transactionService->transfer(
            from: $fromAccount,
            to: $toAccount,
            amount: $amount,
            type: TransactionType::TRANSFER,
            idempotencyKey: Uuid::v4()->toRfc4122(),
            initiatedBy: $user
        );
    }

    public function testReverseTransaction(): void
    {
        // Arrange
        $company = $this->createCompany();
        $user = $this->createUser();

        $fromAccount = $this->createAccount('From Account', $company);
        $toAccount = $this->createAccount('To Account', $company);

        $fromAccount->syncBalance(Money::RUB(10000));

        $this->entityManager->persist($company);
        $this->entityManager->persist($user);
        $this->entityManager->persist($fromAccount);
        $this->entityManager->persist($toAccount);
        $this->entityManager->flush();

        $amount = Money::RUB(1000);

        // Создаем оригинальную транзакцию
        $originalTransaction = $this->transactionService->transfer(
            from: $fromAccount,
            to: $toAccount,
            amount: $amount,
            type: TransactionType::TRANSFER,
            idempotencyKey: Uuid::v4()->toRfc4122(),
            initiatedBy: $user
        );

        $this->assertEquals(9000.0, $fromAccount->getBalance()->toFloat());
        $this->assertEquals(1000.0, $toAccount->getBalance()->toFloat());

        // Act - реверсируем транзакцию
        $reversalTransaction = $this->transactionService->reverse(
            original: $originalTransaction,
            initiatedBy: $user,
            reason: 'Refund requested by customer',
            idempotencyKey: Uuid::v4()->toRfc4122()
        );

        // Assert
        $this->assertInstanceOf(Transaction::class, $reversalTransaction);
        $this->assertTrue($reversalTransaction->isCompleted());
        $this->assertTrue($originalTransaction->isReversed());

        // Проверим что балансы вернулись к исходным
        $this->assertEquals(10000.0, $fromAccount->getBalance()->toFloat());
        $this->assertEquals(0.0, $toAccount->getBalance()->toFloat());
    }

    private function createCompany(): Company
    {
        return new Company(
            id: Uuid::v4(),
            name: 'Test Company',
            inn: '1234567890',
            type: 'supplier',
            legalAddress: 'Test Address'
        );
    }

    private function createUser(): User
    {
        $company = $this->createCompany();

        return new User(
            id: Uuid::v4(),
            email: 'test@example.com',
            password: 'hashed_password',
            firstName: 'Test',
            lastName: 'User',
            company: $company
        );
    }

    private function createAccount(string $name, Company $company): Account
    {
        return new Account(
            $name,
            Uuid::v4(),
            $company,
            Currency::RUB
        );
    }
}
