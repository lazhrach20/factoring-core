<?php

declare(strict_types=1);

namespace App\Shared\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Обработчик исключений для API endpoints
 * Конвертирует исключения в JSON-ответы
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final readonly class JsonExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Обрабатываем только API запросы
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;

        // Обработка ошибок валидации
        if ($exception->getPrevious() instanceof ValidationFailedException) {
            $validationException = $exception->getPrevious();
            $violations = $validationException->getViolations();
            $errors = [];

            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            $response = new JsonResponse([
                'error' => 'Ошибка валидации данных',
                'violations' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

            $event->setResponse($response);
            return;
        }

        // Обработка HTTP исключений
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        }

        // Формируем JSON ответ
        $responseData = [
            'error' => $exception->getMessage() ?: 'Произошла ошибка',
        ];

        // В dev режиме добавляем больше информации
        if ($_ENV['APP_ENV'] === 'dev') {
            $responseData['trace'] = $exception->getTraceAsString();
        }

        $response = new JsonResponse($responseData, $statusCode);
        $event->setResponse($response);
    }
}
