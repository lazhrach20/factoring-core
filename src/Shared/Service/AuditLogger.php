<?php

namespace App\Shared\Service;

use App\Identity\Entity\User;
use App\Shared\Entity\AuditLog;
use App\Shared\Repository\AuditLogRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

class AuditLogger
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository
    ) {
    }

    /**
     * Log an action
     *
     * @param string $action - Action name (e.g., 'user.login', 'transaction.created', 'account.accessed')
     * @param Request $request - HTTP Request object
     * @param User|null $user - User who performed the action
     * @param string|null $entityType - Entity type (e.g., 'Transaction', 'User', 'Account')
     * @param Uuid|null $entityId - Entity ID
     * @param array|null $payload - Additional data to log
     * @param int|null $statusCode - HTTP status code
     * @param string|null $errorMessage - Error message if action failed
     */
    public function log(
        string $action,
        Request $request,
        ?User $user = null,
        ?string $entityType = null,
        ?Uuid $entityId = null,
        ?array $payload = null,
        ?int $statusCode = null,
        ?string $errorMessage = null
    ): void {
        $auditLog = new AuditLog();
        $auditLog->setAction($action)
            ->setUser($user)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setPayload($this->sanitizePayload($payload))
            ->setIpAddress($this->getClientIp($request))
            ->setUserAgent($request->headers->get('User-Agent'))
            ->setHttpMethod($request->getMethod())
            ->setRequestUri($request->getRequestUri())
            ->setStatusCode($statusCode)
            ->setErrorMessage($errorMessage);

        $this->auditLogRepository->save($auditLog);
    }

    /**
     * Log successful action
     */
    public function logSuccess(
        string $action,
        Request $request,
        ?User $user = null,
        ?string $entityType = null,
        ?Uuid $entityId = null,
        ?array $payload = null
    ): void {
        $this->log(
            action: $action,
            request: $request,
            user: $user,
            entityType: $entityType,
            entityId: $entityId,
            payload: $payload,
            statusCode: 200
        );
    }

    /**
     * Log failed action
     */
    public function logFailure(
        string $action,
        Request $request,
        int $statusCode,
        string $errorMessage,
        ?User $user = null,
        ?string $entityType = null,
        ?Uuid $entityId = null,
        ?array $payload = null
    ): void {
        $this->log(
            action: $action,
            request: $request,
            user: $user,
            entityType: $entityType,
            entityId: $entityId,
            payload: $payload,
            statusCode: $statusCode,
            errorMessage: $errorMessage
        );
    }

    /**
     * Log authentication event
     */
    public function logAuth(
        string $action,
        Request $request,
        ?User $user = null,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $this->log(
            action: $action,
            request: $request,
            user: $user,
            statusCode: $success ? 200 : 401,
            errorMessage: $errorMessage
        );
    }

    /**
     * Log access denied event
     */
    public function logAccessDenied(
        string $action,
        Request $request,
        ?User $user = null,
        ?string $entityType = null,
        ?Uuid $entityId = null
    ): void {
        $this->log(
            action: $action,
            request: $request,
            user: $user,
            entityType: $entityType,
            entityId: $entityId,
            statusCode: 403,
            errorMessage: 'Access Denied'
        );
    }

    /**
     * Get client IP address
     */
    private function getClientIp(Request $request): ?string
    {
        $ip = $request->getClientIp();

        // Limit to 45 characters (IPv6 max length)
        if ($ip && strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        return $ip;
    }

    /**
     * Sanitize payload to remove sensitive data
     */
    private function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitiveFields = [
            'password',
            'passwordConfirm',
            'currentPassword',
            'newPassword',
            'token',
            'accessToken',
            'refreshToken',
            'apiKey',
            'secret',
            'authorization'
        ];

        $sanitized = [];

        foreach ($payload as $key => $value) {
            $lowerKey = strtolower($key);

            // Check if field is sensitive
            $isSensitive = false;
            foreach ($sensitiveFields as $sensitiveField) {
                if (str_contains($lowerKey, strtolower($sensitiveField))) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                // Recursively sanitize nested arrays
                $sanitized[$key] = $this->sanitizePayload($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Log action without HTTP request context (for async processing)
     */
    public function logAsync(
        string $action,
        ?User $user = null,
        ?string $entityType = null,
        ?Uuid $entityId = null,
        ?array $payload = null,
        ?int $statusCode = 200,
        ?string $errorMessage = null
    ): void {
        $auditLog = new AuditLog();
        $auditLog->setAction($action)
            ->setUser($user)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setPayload($this->sanitizePayload($payload))
            ->setIpAddress(null)
            ->setUserAgent('Async Worker')
            ->setHttpMethod('ASYNC')
            ->setRequestUri('/async/' . $action)
            ->setStatusCode($statusCode)
            ->setErrorMessage($errorMessage);

        $this->auditLogRepository->save($auditLog);
    }

    /**
     * Generate action names for common operations
     */
    public static function actionCreated(string $entity): string
    {
        return strtolower($entity) . '.created';
    }

    public static function actionUpdated(string $entity): string
    {
        return strtolower($entity) . '.updated';
    }

    public static function actionDeleted(string $entity): string
    {
        return strtolower($entity) . '.deleted';
    }

    public static function actionViewed(string $entity): string
    {
        return strtolower($entity) . '.viewed';
    }

    public static function actionListed(string $entity): string
    {
        return strtolower($entity) . '.listed';
    }
}
