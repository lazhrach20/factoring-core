<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\ValueObject;

use App\Shared\ValueObject\Currency;
use App\Shared\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testCreateFromFloat(): void
    {
        $money = Money::fromFloat(100.50, Currency::RUB);

        $this->assertEquals(10050, $money->getMinorUnits());
        $this->assertEquals(100.50, $money->toFloat());
        $this->assertEquals(Currency::RUB, $money->getCurrency());
    }

    public function testCreateFromMinorUnits(): void
    {
        $money = Money::fromMinorUnits(10050, Currency::RUB);

        $this->assertEquals(10050, $money->getMinorUnits());
        $this->assertEquals(100.50, $money->toFloat());
    }

    public function testStaticFactoryMethods(): void
    {
        $rub = Money::RUB(100);
        $this->assertEquals(Currency::RUB, $rub->getCurrency());
        $this->assertEquals(100.0, $rub->toFloat());

        $usd = Money::USD(50);
        $this->assertEquals(Currency::USD, $usd->getCurrency());

        $eur = Money::EUR(75);
        $this->assertEquals(Currency::EUR, $eur->getCurrency());

        $cny = Money::CNY(200);
        $this->assertEquals(Currency::CNY, $cny->getCurrency());
    }

    public function testZeroFactory(): void
    {
        $zero = Money::zero(Currency::RUB);

        $this->assertTrue($zero->isZero());
        $this->assertEquals(0, $zero->getMinorUnits());
    }

    public function testNegativeAmountThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        Money::fromFloat(-10.0, Currency::RUB);
    }

    public function testAddition(): void
    {
        $money1 = Money::RUB(100);
        $money2 = Money::RUB(50);

        $result = $money1->add($money2);

        $this->assertEquals(150.0, $result->toFloat());
        // Проверка immutability
        $this->assertEquals(100.0, $money1->toFloat());
        $this->assertEquals(50.0, $money2->toFloat());
    }

    public function testSubtraction(): void
    {
        $money1 = Money::RUB(100);
        $money2 = Money::RUB(30);

        $result = $money1->subtract($money2);

        $this->assertEquals(70.0, $result->toFloat());
    }

    public function testSubtractionResultingInNegativeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subtraction result cannot be negative');

        $money1 = Money::RUB(30);
        $money2 = Money::RUB(100);

        $money1->subtract($money2);
    }

    public function testMultiplication(): void
    {
        $money = Money::RUB(100);

        $result = $money->multiply(2.5);

        $this->assertEquals(250.0, $result->toFloat());
    }

    public function testDivision(): void
    {
        $money = Money::RUB(100);

        $result = $money->divide(4);

        $this->assertEquals(25.0, $result->toFloat());
    }

    public function testDivisionByZeroThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Divisor must be positive');

        $money = Money::RUB(100);
        $money->divide(0);
    }

    public function testEquals(): void
    {
        $money1 = Money::RUB(100);
        $money2 = Money::RUB(100);
        $money3 = Money::RUB(50);

        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3));
    }

    public function testComparisons(): void
    {
        $money1 = Money::RUB(100);
        $money2 = Money::RUB(50);
        $money3 = Money::RUB(100);

        $this->assertTrue($money1->greaterThan($money2));
        $this->assertFalse($money2->greaterThan($money1));

        $this->assertTrue($money1->greaterThanOrEqual($money3));
        $this->assertTrue($money1->greaterThanOrEqual($money2));

        $this->assertTrue($money2->lessThan($money1));
        $this->assertFalse($money1->lessThan($money2));

        $this->assertTrue($money1->lessThanOrEqual($money3));
        $this->assertTrue($money2->lessThanOrEqual($money1));
    }

    public function testIsZero(): void
    {
        $zero = Money::zero(Currency::RUB);
        $nonZero = Money::RUB(1);

        $this->assertTrue($zero->isZero());
        $this->assertFalse($nonZero->isZero());
    }

    public function testIsPositive(): void
    {
        $positive = Money::RUB(1);
        $zero = Money::zero(Currency::RUB);

        $this->assertTrue($positive->isPositive());
        $this->assertFalse($zero->isPositive());
    }

    public function testIsNegative(): void
    {
        $positive = Money::RUB(1);

        // Money не может быть отрицательным (конструктор выбросит исключение)
        $this->assertFalse($positive->isNegative());
    }

    public function testCurrencyMismatchInAdditionThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot perform operation on different currencies');

        $rub = Money::RUB(100);
        $usd = Money::USD(100);

        $rub->add($usd);
    }

    public function testCurrencyMismatchInSubtractionThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot perform operation on different currencies');

        $rub = Money::RUB(100);
        $usd = Money::USD(50);

        $rub->subtract($usd);
    }

    public function testFormat(): void
    {
        $money = Money::RUB(1000.50);

        $formatted = $money->format();

        $this->assertStringContainsString('1 000.50', $formatted);
        $this->assertStringContainsString('₽', $formatted);
    }

    public function testToString(): void
    {
        $money = Money::RUB(100);

        $string = (string) $money;

        $this->assertStringContainsString('100.00', $string);
    }

    public function testJsonSerialize(): void
    {
        $money = Money::RUB(100.50);

        $json = $money->jsonSerialize();

        $this->assertArrayHasKey('amount', $json);
        $this->assertArrayHasKey('currency', $json);
        $this->assertEquals(100.50, $json['amount']);
        $this->assertEquals('RUB', $json['currency']);
    }
}
