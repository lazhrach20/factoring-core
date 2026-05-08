<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetNewPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Токен не может быть пустым')]
        public string $token,

        #[Assert\NotBlank(message: 'Пароль не может быть пустым')]
        #[Assert\Length(
            min: 8,
            max: 255,
            minMessage: 'Пароль должен содержать минимум {{ limit }} символов',
            maxMessage: 'Пароль не может быть длиннее {{ limit }} символов'
        )]
        #[Assert\Regex(
            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            message: 'Пароль должен содержать минимум одну заглавную букву, одну строчную букву и одну цифру'
        )]
        public string $password,

        #[Assert\NotBlank(message: 'Подтверждение пароля не может быть пустым')]
        public string $passwordConfirm,
    ) {
    }
}
