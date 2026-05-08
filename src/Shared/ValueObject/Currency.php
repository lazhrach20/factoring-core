<?php

declare(strict_types=1);

namespace App\Shared\ValueObject;

enum Currency: string
{
    case RUB = 'RUB';
    case USD = 'USD';
    case EUR = 'EUR';
    case CNY = 'CNY'; // Юань - для импорта из Китая

    public function getSymbol(): string
    {
        return match ($this) {
            self::RUB => '₽',
            self::USD => '$',
            self::EUR => '€',
            self::CNY => '¥',
        };
    }

    public function getName(): string
    {
        return match ($this) {
            self::RUB => 'Российский рубль',
            self::USD => 'Доллар США',
            self::EUR => 'Евро',
            self::CNY => 'Китайский юань',
        };
    }
}
