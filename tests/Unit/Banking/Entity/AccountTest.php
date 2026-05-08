<?php

declare(strict_types=1);

namespace App\Tests\Unit\Banking\Entity;

use App\Banking\Entity\Account;
use App\Identity\Entity\Company;
use App\Shared\ValueObject\Currency;
use App\Shared\ValueObject\Money;
use DomainException;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class AccountTest extends TestCase
{
    private Company $company;

    protected function setUp(): void
    {
        $this->company = $this->createMock(Company::class);
    }

    public function testCreateAccount(): void
    {
        $ownerId = Uuid::v4();

        $account = new Account(
            'Test Account',
            $ownerId,
            $this->company,
            Currency::RUB
        );

        $this->assertEquals('Test Account', $account->getName());
        $this->assertEquals($ownerId, $account->getOwnerId());
        $this->assertEquals($this->company, $account->getCompany());
        $this->assertEquals(Currency::RUB, $account->getCurrency());
        $this->assertTrue($account->getBalance()->isZero());
    }

    public function testGetBalanceReturnsMoneyObject(): void
    {
        $account = $this->createAccount();

        $balance = $account->getBalance();

        $this->assertInstanceOf(Money::class, $balance);
        $this->assertEquals(Currency::RUB, $balance->getCurrency());
    }

    public function testIncreaseBalance(): void
    {
        $account = $this->createAccount();
        $amount = Money::RUB(1000);

        $account->increaseBalance($amount);

        $this->assertEquals(1000.0, $account->getBalance()->toFloat());
        $this->assertEquals(100000, $account->getBalanceMinorUnits());
    }

    public function testDecreaseBalance(): void
    {
        $account = $this->createAccount();

        // Сначала увеличим баланс
        $account->increaseBalance(Money::RUB(1000));

        // Затем уменьшим
        $account->decreaseBalance(Money::RUB(300));

        $this->assertEquals(700.0, $account->getBalance()->toFloat());
    }

    public function testDecreaseBalanceBelowZeroThrowsException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient funds');

        $account = $this->createAccount();
        $account->increaseBalance(Money::RUB(100));

        // Попытка снять больше, чем есть
        $account->decreaseBalance(Money::RUB(200));
    }

    public function testIncreaseBalanceWithNegativeAmountThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot increase balance by negative amount');

        $account = $this->createAccount();

        // Попытка увеличить на отрицательную сумму (хотя Money не позволяет создать отрицательную)
        // Используем reflection для создания "отрицательного" Money
        $negativeMoney = new \ReflectionClass(Money::class);
        $instance = $negativeMoney->newInstanceWithoutConstructor();

        $amountProperty = $negativeMoney->getProperty('amount');
        $amountProperty->setAccessible(true);
        $amountProperty->setValue($instance, -1000);

        $currencyProperty = $negativeMoney->getProperty('currency');
        $currencyProperty->setAccessible(true);
        $currencyProperty->setValue($instance, Currency::RUB);

        $account->increaseBalance($instance);
    }

    public function testCurrencyMismatchInIncreaseThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch');

        $account = $this->createAccount(); // RUB

        $account->increaseBalance(Money::USD(100)); // USD
    }

    public function testCurrencyMismatchInDecreaseThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch');

        $account = $this->createAccount(); // RUB
        $account->increaseBalance(Money::RUB(1000));

        $account->decreaseBalance(Money::USD(100)); // USD
    }

    public function testSyncBalance(): void
    {
        $account = $this->createAccount();

        // Установим баланс вручную
        $calculatedBalance = Money::RUB(5000);
        $account->syncBalance($calculatedBalance);

        $this->assertTrue($account->getBalance()->equals($calculatedBalance));
    }

    public function testSyncBalanceWithWrongCurrencyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch during balance sync');

        $account = $this->createAccount(); // RUB

        $account->syncBalance(Money::USD(100)); // USD
    }

    private function createAccount(): Account
    {
        return new Account(
            'Test Account',
            Uuid::v4(),
            $this->company,
            Currency::RUB
        );
    }
}
