<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Dto\PasswordResetRequest;
use App\Identity\Dto\SetNewPasswordRequest;
use App\Identity\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth/password')]
final class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {
    }

    /**
     * Запросить сброс пароля (отправка email с токеном)
     */
    #[Route('/reset-request', name: 'api_auth_password_reset_request', methods: ['POST'])]
    public function requestReset(
        #[MapRequestPayload] PasswordResetRequest $request,
    ): JsonResponse {
        try {
            $token = $this->passwordResetService->requestReset($request->email);

            return $this->json([
                'message' => 'Если пользователь с таким email существует, на него отправлена инструкция по сбросу пароля',
                // В DEV режиме показываем токен для тестирования
                // В PROD это должно быть удалено!
                'token' => $_ENV['APP_ENV'] === 'dev' ? $token->getToken() : null,
            ]);
        } catch (\Exception $e) {
            // Из соображений безопасности всегда возвращаем одинаковый ответ
            return $this->json([
                'message' => 'Если пользователь с таким email существует, на него отправлена инструкция по сбросу пароля',
            ]);
        }
    }

    /**
     * Сбросить пароль по токену
     */
    #[Route('/reset', name: 'api_auth_password_reset', methods: ['POST'])]
    public function resetPassword(
        #[MapRequestPayload] SetNewPasswordRequest $request,
    ): JsonResponse {
        try {
            $this->passwordResetService->resetPassword($request);

            return $this->json([
                'message' => 'Пароль успешно изменен',
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Ошибка при сбросе пароля',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Проверить валидность токена
     */
    #[Route('/validate-token', name: 'api_auth_password_validate_token', methods: ['POST'])]
    public function validateToken(
        #[MapRequestPayload] array $data,
    ): JsonResponse {
        $token = $data['token'] ?? '';

        if (empty($token)) {
            return $this->json([
                'valid' => false,
                'error' => 'Токен не предоставлен',
            ], Response::HTTP_BAD_REQUEST);
        }

        $isValid = $this->passwordResetService->validateToken($token);

        return $this->json([
            'valid' => $isValid,
        ]);
    }
}
