<?php

declare(strict_types=1);

namespace App\Shared\Exception;

use App\Shared\ValueObject\Money;

/**
 * Исключение выбрасывается когда на счете недостаточно средств для операции
 */
class InsufficientFundsException extends \DomainException
{
    public function __construct(
        private readonly Money $required,
        private readonly Money $available,
        string $message = 'Insufficient funds',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $fullMessage = sprintf(
            '%s: required %s, available %s',
            $message,
            $required->format(),
            $available->format()
        );

        parent::__construct($fullMessage, $code, $previous);
    }

    public function getRequired(): Money
    {
        return $this->required;
    }

    public function getAvailable(): Money
    {
        return $this->available;
    }
}
