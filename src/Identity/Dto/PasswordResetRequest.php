<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PasswordResetRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email не может быть пустым')]
        #[Assert\Email(message: 'Некорректный email адрес')]
        public string $email,
    ) {
    }
}
