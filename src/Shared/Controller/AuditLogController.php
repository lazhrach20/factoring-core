<?php

namespace App\Shared\Controller;

use App\Identity\Repository\UserRepository;
use App\Shared\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/audit-logs')]
#[IsGranted('AUDIT_LOG_VIEW')]
class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * Проверяет, является ли текущий пользователь представителем фактора
     */
    private function checkFactorAccess(): ?JsonResponse
    {
        $currentUser = $this->getUser();
        $currentCompany = $currentUser->getCompany();

        if ($currentCompany->getType() !== 'factor') {
            return $this->json(
                ['error' => 'Доступ запрещен. Только фактор может просматривать журнал аудита.'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        return null;
    }

    #[Route('', name: 'api_audit_logs_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        // Parse filters from query parameters
        $filters = $this->parseFilters($request);

        // Get pagination parameters
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 50)));

        // Fetch logs with filters
        $logs = $this->auditLogRepository->findWithFilters($filters, $page, $limit);
        $total = $this->auditLogRepository->countWithFilters($filters);

        // Serialize logs
        $data = array_map(function ($log) {
            $user = $log->getUser();
            return [
                'id' => $log->getId()->toRfc4122(),
                'action' => $log->getAction(),
                'user' => $user ? [
                    'id' => $user->getId()->toRfc4122(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                ] : null,
                'entityType' => $log->getEntityType(),
                'entityId' => $log->getEntityId()?->toRfc4122(),
                'payload' => $log->getPayload(),
                'ipAddress' => $log->getIpAddress(),
                'userAgent' => $log->getUserAgent(),
                'httpMethod' => $log->getHttpMethod(),
                'requestUri' => $log->getRequestUri(),
                'statusCode' => $log->getStatusCode(),
                'errorMessage' => $log->getErrorMessage(),
                'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $logs);

        return $this->json([
            'logs' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    #[Route('/stats', name: 'api_audit_logs_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        // Get date range
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');

        $filters = [];
        if ($startDate) {
            $filters['startDate'] = new \DateTime($startDate);
        }
        if ($endDate) {
            $filters['endDate'] = new \DateTime($endDate);
        }

        $total = $this->auditLogRepository->countWithFilters($filters);

        // Count errors (status code >= 400)
        $errorFilters = array_merge($filters, ['statusCode' => 'error']);
        $errors = $this->auditLogRepository->countWithFilters($errorFilters);

        return $this->json([
            'total' => $total,
            'errors' => $errors,
            'success' => $total - $errors,
        ]);
    }

    #[Route('/{id}', name: 'api_audit_logs_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        $log = $this->auditLogRepository->find($id);

        if (!$log) {
            return $this->json(['error' => 'Audit log not found'], 404);
        }

        $user = $log->getUser();

        return $this->json([
            'id' => $log->getId()->toRfc4122(),
            'action' => $log->getAction(),
            'user' => $user ? [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'company' => [
                    'id' => $user->getCompany()->getId()->toRfc4122(),
                    'name' => $user->getCompany()->getName(),
                    'type' => $user->getCompany()->getType(),
                ],
            ] : null,
            'entityType' => $log->getEntityType(),
            'entityId' => $log->getEntityId()?->toRfc4122(),
            'payload' => $log->getPayload(),
            'ipAddress' => $log->getIpAddress(),
            'userAgent' => $log->getUserAgent(),
            'httpMethod' => $log->getHttpMethod(),
            'requestUri' => $log->getRequestUri(),
            'statusCode' => $log->getStatusCode(),
            'errorMessage' => $log->getErrorMessage(),
            'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    private function parseFilters(Request $request): array
    {
        $filters = [];

        // User filter
        $userId = $request->query->get('userId');
        if ($userId) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $filters['user'] = $user;
            }
        }

        // Action filter
        $action = $request->query->get('action');
        if ($action) {
            $filters['action'] = $action;
        }

        // Entity type filter
        $entityType = $request->query->get('entityType');
        if ($entityType) {
            $filters['entityType'] = $entityType;
        }

        // Date range filters
        $startDate = $request->query->get('startDate');
        if ($startDate) {
            try {
                $filters['startDate'] = new \DateTime($startDate);
            } catch (\Exception $e) {
                // Invalid date, skip
            }
        }

        $endDate = $request->query->get('endDate');
        if ($endDate) {
            try {
                $filters['endDate'] = new \DateTime($endDate);
            } catch (\Exception $e) {
                // Invalid date, skip
            }
        }

        // HTTP method filter
        $httpMethod = $request->query->get('httpMethod');
        if ($httpMethod) {
            $filters['httpMethod'] = strtoupper($httpMethod);
        }

        // Status code filter
        $statusCode = $request->query->get('statusCode');
        if ($statusCode) {
            $filters['statusCode'] = $statusCode;
        }

        return $filters;
    }
}
