<?php

declare(strict_types=1);

namespace App\Shared\ValueObject;

use InvalidArgumentException;

/**
 * Money Value Object - неизменяемый объект для работы с деньгами
 * Хранит сумму в минимальных единицах (копейки, центы) для предотвращения потери точности
 */
final class Money
{
    private function __construct(
        private readonly int $amount,      // Сумма в минимальных единицах (копейки/центы)
        private readonly Currency $currency
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }

    /**
     * Создать Money из рублей (или основной валюты)
     */
    public static function fromFloat(float $amount, Currency $currency): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }

        // Конвертируем в минимальные единицы (умножаем на 100)
        $minorUnits = (int) round($amount * 100);

        return new self($minorUnits, $currency);
    }

    /**
     * Создать Money из минимальных единиц (копейки/центы)
     */
    public static function fromMinorUnits(int $minorUnits, Currency $currency): self
    {
        return new self($minorUnits, $currency);
    }

    /**
     * Создать Money в рублях
     */
    public static function RUB(float $rubles): self
    {
        return self::fromFloat($rubles, Currency::RUB);
    }

    /**
     * Создать Money в долларах
     */
    public static function USD(float $dollars): self
    {
        return self::fromFloat($dollars, Currency::USD);
    }

    /**
     * Создать Money в евро
     */
    public static function EUR(float $euros): self
    {
        return self::fromFloat($euros, Currency::EUR);
    }

    /**
     * Создать Money в юанях
     */
    public static function CNY(float $yuan): self
    {
        return self::fromFloat($yuan, Currency::CNY);
    }

    /**
     * Создать нулевую сумму
     */
    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Получить сумму в минимальных единицах (копейки/центы)
     */
    public function getMinorUnits(): int
    {
        return $this->amount;
    }

    /**
     * Получить сумму в основной валюте (рубли/доллары)
     */
    public function toFloat(): float
    {
        return $this->amount / 100;
    }

    /**
     * Получить валюту
     */
    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    /**
     * Сложить с другой суммой
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self(
            $this->amount + $other->amount,
            $this->currency
        );
    }

    /**
     * Вычесть другую сумму
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        $result = $this->amount - $other->amount;

        if ($result < 0) {
            throw new InvalidArgumentException('Subtraction result cannot be negative');
        }

        return new self($result, $this->currency);
    }

    /**
     * Умножить на коэффициент
     */
    public function multiply(float $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Multiplier cannot be negative');
        }

        $result = (int) round($this->amount * $multiplier);

        return new self($result, $this->currency);
    }

    /**
     * Разделить на делитель
     */
    public function divide(float $divisor): self
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Divisor must be positive');
        }

        $result = (int) round($this->amount / $divisor);

        return new self($result, $this->currency);
    }

    /**
     * Проверка равенства
     */
    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    /**
     * Больше чем
     */
    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    /**
     * Больше или равно
     */
    public function greaterThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount >= $other->amount;
    }

    /**
     * Меньше чем
     */
    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    /**
     * Меньше или равно
     */
    public function lessThanOrEqual(Money $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount <= $other->amount;
    }

    /**
     * Проверка на ноль
     */
    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    /**
     * Проверка на положительное значение
     */
    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Проверка на отрицательное значение
     */
    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Форматированная строка с символом валюты
     */
    public function format(): string
    {
        return sprintf(
            '%s %s',
            number_format($this->toFloat(), 2, '.', ' '),
            $this->currency->getSymbol()
        );
    }

    /**
     * Строковое представление
     */
    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * Для JSON сериализации
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->toFloat(),
            'currency' => $this->currency->value,
            'formatted' => $this->format(),
        ];
    }

    /**
     * Проверка одинаковой валюты
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot perform operation on different currencies: %s and %s',
                    $this->currency->value,
                    $other->currency->value
                )
            );
        }
    }
}
