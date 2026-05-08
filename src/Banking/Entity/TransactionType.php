<?php

declare(strict_types=1);

namespace App\Banking\Entity;

enum TransactionType: string
{
    case FINANCING = 'financing';           // Финансирование (от фактора к поставщику)
    case REPAYMENT = 'repayment';           // Погашение (от дебитора к фактору)
    case FEE = 'fee';                       // Комиссия
    case INTEREST = 'interest';             // Проценты
    case PENALTY = 'penalty';               // Штраф/пеня
    case REFUND = 'refund';                 // Возврат
    case WITHDRAWAL = 'withdrawal';         // Вывод средств
    case DEPOSIT = 'deposit';               // Пополнение
    case TRANSFER = 'transfer';             // Перевод между счетами
    case ADJUSTMENT = 'adjustment';         // Корректировка

    public function getLabel(): string
    {
        return match ($this) {
            self::FINANCING => 'Финансирование',
            self::REPAYMENT => 'Погашение',
            self::FEE => 'Комиссия',
            self::INTEREST => 'Проценты',
            self::PENALTY => 'Штраф',
            self::REFUND => 'Возврат',
            self::WITHDRAWAL => 'Вывод средств',
            self::DEPOSIT => 'Пополнение',
            self::TRANSFER => 'Перевод',
            self::ADJUSTMENT => 'Корректировка',
        };
    }
}
