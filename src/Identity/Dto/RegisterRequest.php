<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email не может быть пустым')]
        #[Assert\Email(message: 'Некорректный email адрес')]
        public string $email,

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

        #[Assert\NotBlank(message: 'Имя не может быть пустым')]
        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'Имя должно содержать минимум {{ limit }} символа',
            maxMessage: 'Имя не может быть длиннее {{ limit }} символов'
        )]
        public string $firstName,

        #[Assert\NotBlank(message: 'Фамилия не может быть пустой')]
        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'Фамилия должна содержать минимум {{ limit }} символа',
            maxMessage: 'Фамилия не может быть длиннее {{ limit }} символов'
        )]
        public string $lastName,

        #[Assert\NotBlank(message: 'Телефон не может быть пустым')]
        #[Assert\Regex(
            pattern: '/^\+?[1-9]\d{10,14}$/',
            message: 'Некорректный формат телефона'
        )]
        public string $phone,

        #[Assert\NotBlank(message: 'ID компании не может быть пустым')]
        #[Assert\Uuid(message: 'Некорректный ID компании')]
        public string $companyId,
    ) {
    }
}
