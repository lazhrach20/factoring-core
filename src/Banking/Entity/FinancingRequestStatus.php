<?php

declare(strict_types=1);

namespace App\Banking\Entity;

enum FinancingRequestStatus: string
{
    case PENDING = 'pending';      // Ожидает одобрения дебитора
    case APPROVED = 'approved';    // Одобрено дебитором
    case FUNDED = 'funded';        // Профинансировано (деньги переведены)
    case REJECTED = 'rejected';    // Отклонено
    case CANCELLED = 'cancelled';  // Отменено

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Ожидает одобрения',
            self::APPROVED => 'Одобрено',
            self::FUNDED => 'Профинансировано',
            self::REJECTED => 'Отклонено',
            self::CANCELLED => 'Отменено',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::FUNDED, self::REJECTED, self::CANCELLED]);
    }

    public function canApprove(): bool
    {
        return $this === self::PENDING;
    }

    public function canFund(): bool
    {
        return $this === self::APPROVED;
    }
}
