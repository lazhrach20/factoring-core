<?php

declare(strict_types=1);

namespace App\Identity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class AssignRoleRequest
{
    #[Assert\NotBlank(message: 'Email обязателен')]
    #[Assert\Email(message: 'Некорректный email')]
    public string $email;

    #[Assert\NotBlank(message: 'Роль обязательна')]
    public string $roleName;
}
