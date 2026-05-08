<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Dto\RegisterRequest;
use App\Identity\Entity\User;
use App\Identity\Repository\CompanyRepository;
use App\Identity\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegistrationService
{
    public function __construct(
        private UserRepository $userRepository,
        private CompanyRepository $companyRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @throws \RuntimeException
     */
    public function register(RegisterRequest $request): User
    {
        // Проверка совпадения паролей
        if ($request->password !== $request->passwordConfirm) {
            throw new \RuntimeException('Пароли не совпадают');
        }

        // Проверка существования email
        if ($this->userRepository->emailExists($request->email)) {
            throw new \RuntimeException('Пользователь с таким email уже существует');
        }

        // Проверка существования компании
        $company = $this->companyRepository->find($request->companyId);
        if (!$company) {
            throw new \RuntimeException('Компания не найдена');
        }

        // Создание пользователя
        $user = new User(
            id: \Symfony\Component\Uid\Uuid::v4(),
            email: $request->email,
            password: '', // Placeholder: overwritten immediately below by the hashed password
            firstName: $request->firstName,
            lastName: $request->lastName,
            company: $company
        );

        // Установка телефона
        $user->setPhone($request->phone);

        // Хеширование пароля
        $hashedPassword = $this->passwordHasher->hashPassword($user, $request->password);
        $user->setPassword($hashedPassword);

        // Сохранение
        $this->userRepository->save($user, flush: true);

        return $user;
    }
}
