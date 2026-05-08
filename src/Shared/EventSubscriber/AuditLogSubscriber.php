<?php

namespace App\Shared\EventSubscriber;

use App\Shared\Service\AuditLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AuditLogSubscriber implements EventSubscriberInterface
{
    private const AUDITED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const EXCLUDED_PATHS = [
        '/api/auth/refresh',
        '/_profiler',
        '/_wdt',
    ];

    private const IMPORTANT_GET_PATHS = [
        '/api/users/me/accounts',
        '/api/banking/accounts',
        '/api/audit-logs',
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly Security $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Skip excluded paths
        foreach (self::EXCLUDED_PATHS as $excludedPath) {
            if (str_starts_with($request->getPathInfo(), $excludedPath)) {
                return;
            }
        }

        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $statusCode = $response->getStatusCode();

        // Skip non-API paths
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Determine if this request should be audited
        $shouldAudit = in_array($method, self::AUDITED_METHODS);

        // Also audit important GET requests
        if ($method === 'GET') {
            foreach (self::IMPORTANT_GET_PATHS as $importantPath) {
                if (str_starts_with($path, $importantPath)) {
                    $shouldAudit = true;
                    break;
                }
            }
        }

        if (!$shouldAudit) {
            return;
        }

        $user = $this->security->getUser();
        $action = $this->generateActionName($method, $path);

        // Extract entity info from path
        [$entityType, $entityId] = $this->extractEntityFromPath($path);

        // Get request payload
        $payload = $this->getRequestPayload($request);

        $this->auditLogger->log(
            action: $action,
            request: $request,
            user: $user,
            entityType: $entityType,
            entityId: $entityId,
            payload: $payload,
            statusCode: $statusCode
        );
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $exception = $event->getThrowable();

        // Skip excluded paths
        foreach (self::EXCLUDED_PATHS as $excludedPath) {
            if (str_starts_with($request->getPathInfo(), $excludedPath)) {
                return;
            }
        }

        $path = $request->getPathInfo();

        // Only log API exceptions
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $user = $this->security->getUser();

        // Log access denied exceptions
        if ($exception instanceof AccessDeniedException) {
            $action = $this->generateActionName($request->getMethod(), $path);
            [$entityType, $entityId] = $this->extractEntityFromPath($path);

            $this->auditLogger->logAccessDenied(
                action: $action . '.access_denied',
                request: $request,
                user: $user,
                entityType: $entityType,
                entityId: $entityId
            );
        } else {
            // Log other exceptions
            $action = $this->generateActionName($request->getMethod(), $path);
            [$entityType, $entityId] = $this->extractEntityFromPath($path);

            $statusCode = method_exists($exception, 'getStatusCode')
                ? $exception->getStatusCode()
                : 500;

            $this->auditLogger->logFailure(
                action: $action . '.error',
                request: $request,
                statusCode: $statusCode,
                errorMessage: $exception->getMessage(),
                user: $user,
                entityType: $entityType,
                entityId: $entityId
            );
        }
    }

    private function generateActionName(string $method, string $path): string
    {
        // Remove /api/ prefix
        $path = preg_replace('#^/api/#', '', $path);

        // Remove UUIDs from path
        $path = preg_replace(
            '#[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}#i',
            '{id}',
            $path
        );

        // Replace slashes with dots
        $path = str_replace('/', '.', $path);

        // Add method prefix
        $methodMap = [
            'POST' => 'create',
            'PUT' => 'update',
            'PATCH' => 'update',
            'DELETE' => 'delete',
            'GET' => 'view',
        ];

        $prefix = $methodMap[$method] ?? strtolower($method);

        return $prefix . '.' . $path;
    }

    private function extractEntityFromPath(string $path): array
    {
        // Try to extract entity type and ID from path
        // Examples:
        // /api/banking/accounts/123 -> ['Account', '123']
        // /api/users/123 -> ['User', '123']
        // /api/transactions -> ['Transaction', null]

        $parts = explode('/', trim($path, '/'));

        // Remove 'api' prefix
        if ($parts[0] === 'api') {
            array_shift($parts);
        }

        $entityType = null;
        $entityId = null;

        // Check if last part is a UUID
        $lastPart = end($parts);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $lastPart)) {
            $entityId = \Symfony\Component\Uid\Uuid::fromString($lastPart);
            array_pop($parts);
        }

        // Get entity type from remaining path
        if (!empty($parts)) {
            $entityType = ucfirst(end($parts));
            // Remove plural 's'
            if (str_ends_with($entityType, 's')) {
                $entityType = substr($entityType, 0, -1);
            }
        }

        return [$entityType, $entityId];
    }

    private function getRequestPayload($request): ?array
    {
        $payload = [];

        // Get JSON body
        $contentType = $request->headers->get('Content-Type');
        if ($contentType && str_contains($contentType, 'application/json')) {
            $content = $request->getContent();
            if ($content) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $payload = array_merge($payload, $data);
                }
            }
        }

        // Get query parameters
        if ($request->query->count() > 0) {
            $payload['query'] = $request->query->all();
        }

        return !empty($payload) ? $payload : null;
    }
}
