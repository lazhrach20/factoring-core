<?php

declare(strict_types=1);

namespace App\Banking\Entity;

enum TransactionStatus: string
{
    case PENDING = 'pending';       // Ожидает обработки
    case PROCESSING = 'processing'; // В процессе
    case COMPLETED = 'completed';   // Завершена
    case FAILED = 'failed';         // Ошибка
    case CANCELLED = 'cancelled';   // Отменена
    case REVERSED = 'reversed';     // Отменена (реверс)

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает обработки',
            self::PROCESSING => 'В процессе',
            self::COMPLETED => 'Завершена',
            self::FAILED => 'Ошибка',
            self::CANCELLED => 'Отменена',
            self::REVERSED => 'Возврат',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
            self::REVERSED,
        ]);
    }
}
