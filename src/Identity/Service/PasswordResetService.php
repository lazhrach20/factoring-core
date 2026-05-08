<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Dto\SetNewPasswordRequest;
use App\Identity\Entity\PasswordResetToken;
use App\Identity\Repository\PasswordResetTokenRepository;
use App\Identity\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PasswordResetService
{
    private const TOKEN_VALIDITY_HOURS = 1;

    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetTokenRepository $tokenRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private SecurityVersionService $securityVersionService,
    ) {
    }

    /**
     * Создать токен сброса пароля
     *
     * @throws \RuntimeException
     */
    public function requestReset(string $email): PasswordResetToken
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            // Из соображений безопасности не сообщаем, что пользователь не найден
            throw new \RuntimeException('Если пользователь с таким email существует, на него будет отправлена инструкция');
        }

        if ($user->isBlocked()) {
            throw new \RuntimeException('Ваш аккаунт заблокирован');
        }

        // Удаляем все старые токены пользователя
        $this->tokenRepository->deleteByUser($user);

        // Генерируем новый токен
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable())->modify('+' . self::TOKEN_VALIDITY_HOURS . ' hours');

        $resetToken = new PasswordResetToken($user, $token, $expiresAt);
        $this->tokenRepository->save($resetToken);

        // TODO: Send reset-password email with a link containing the token

        return $resetToken;
    }

    /**
     * Сбросить пароль по токену
     *
     * @throws \RuntimeException
     */
    public function resetPassword(SetNewPasswordRequest $request): void
    {
        // Проверка совпадения паролей
        if ($request->password !== $request->passwordConfirm) {
            throw new \RuntimeException('Пароли не совпадают');
        }

        // Поиск валидного токена
        $resetToken = $this->tokenRepository->findValidToken($request->token);
        if (!$resetToken) {
            throw new \RuntimeException('Недействительный или истекший токен');
        }

        $user = $resetToken->getUser();

        if ($user->isBlocked()) {
            throw new \RuntimeException('Ваш аккаунт заблокирован');
        }

        // Установка нового пароля
        $hashedPassword = $this->passwordHasher->hashPassword($user, $request->password);
        $user->setPassword($hashedPassword);

        // Increment security version to invalidate all existing tokens
        $this->securityVersionService->incrementSecurityVersion($user);

        // Отметка токена как использованного
        $resetToken->markAsUsed();

        // Сохранение
        $this->userRepository->save($user, flush: true);
        $this->tokenRepository->save($resetToken);
    }

    /**
     * Проверить валидность токена
     */
    public function validateToken(string $token): bool
    {
        $resetToken = $this->tokenRepository->findValidToken($token);
        return $resetToken !== null;
    }
}
