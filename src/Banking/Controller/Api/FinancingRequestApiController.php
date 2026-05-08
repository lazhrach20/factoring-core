<?php

declare(strict_types=1);

namespace App\Banking\Controller\Api;

use App\Banking\Entity\Account;
use App\Banking\Entity\FinancingRequest;
use App\Banking\Entity\FinancingRequestStatus;
use App\Banking\Repository\FinancingRequestRepository;
use App\Banking\Service\TransactionService;
use App\Identity\Entity\Company;
use App\Identity\Entity\User;
use App\Shared\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/financing-requests', name: 'api_financing_requests_')]
#[IsGranted('ROLE_ADMIN')] // Только администраторы могут работать с заявками
class FinancingRequestApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FinancingRequestRepository $repository,
        private readonly TransactionService $transactionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Получить список заявок
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (!$company) {
            return new JsonResponse(['error' => 'User has no company'], Response::HTTP_FORBIDDEN);
        }

        $status = $request->query->get('status');
        $statusEnum = $status ? FinancingRequestStatus::tryFrom($status) : null;

        $requests = $this->repository->findByCompany($company, $statusEnum);

        return new JsonResponse([
            'requests' => array_map([$this, 'formatRequest'], $requests),
        ]);
    }

    /**
     * Получить детали заявки
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $request = $this->repository->find($id);
        if (!$request) {
            return new JsonResponse(['error' => 'Request not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$request->canBeViewedBy($user)) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->formatRequest($request));
    }

    /**
     * Создать новую заявку (только фактор)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('FINANCING_REQUEST_CREATE')]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        // Валидация
        if (!isset($data['amountMinorUnits'], $data['currency'], $data['supplierCompanyId'],
                   $data['debtorCompanyId'], $data['factorAccountId'], $data['supplierAccountId'])) {
            return new JsonResponse(
                ['error' => 'Missing required fields'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Получить сущности
        $supplierCompany = $this->entityManager->getRepository(Company::class)->find($data['supplierCompanyId']);
        $debtorCompany = $this->entityManager->getRepository(Company::class)->find($data['debtorCompanyId']);
        $factorAccount = $this->entityManager->getRepository(Account::class)->find($data['factorAccountId']);
        $supplierAccount = $this->entityManager->getRepository(Account::class)->find($data['supplierAccountId']);

        if (!$supplierCompany || !$debtorCompany || !$factorAccount || !$supplierAccount) {
            return new JsonResponse(
                ['error' => 'Invalid company or account IDs'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Проверить что счет фактора принадлежит компании фактора (пользователя)
        if ($factorAccount->getCompany() !== $user->getCompany()) {
            return new JsonResponse(
                ['error' => 'Factor account must belong to your company'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Проверить что счет поставщика принадлежит поставщику
        if ($supplierAccount->getCompany() !== $supplierCompany) {
            return new JsonResponse(
                ['error' => 'Supplier account must belong to supplier company'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $amount = Money::fromMinorUnits(
                (int) $data['amountMinorUnits'],
                \App\Shared\ValueObject\Currency::from((string) $data['currency'])
            );

            $financingRequest = new FinancingRequest(
                amount: $amount,
                supplierCompany: $supplierCompany,
                debtorCompany: $debtorCompany,
                factorAccount: $factorAccount,
                supplierAccount: $supplierAccount,
                createdBy: $user,
                description: $data['description'] ?? null,
            );

            $this->entityManager->persist($financingRequest);
            $this->entityManager->flush();

            $this->logger->info('Financing request created', [
                'request_id' => $financingRequest->getId()->toRfc4122(),
                'supplier' => $supplierCompany->getName(),
                'debtor' => $debtorCompany->getName(),
                'amount' => $data['amountMinorUnits'],
            ]);

            return new JsonResponse($this->formatRequest($financingRequest), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create financing request', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(
                ['error' => 'Failed to create request: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Одобрить заявку (только фактор)
     */
    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    #[IsGranted('FINANCING_REQUEST_APPROVE')]
    public function approve(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Проверяем, что пользователь из компании-фактора
        if ($user->getCompany()->getType() !== 'factor') {
            return new JsonResponse(
                ['error' => 'Только фактор может одобрять заявки'],
                Response::HTTP_FORBIDDEN
            );
        }

        $financingRequest = $this->repository->find($id);
        if (!$financingRequest) {
            return new JsonResponse(['error' => 'Request not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$financingRequest->canBeApprovedBy($user)) {
            return new JsonResponse(
                ['error' => 'You cannot approve this request'],
                Response::HTTP_FORBIDDEN
            );
        }

        try {
            $this->entityManager->beginTransaction();

            // Одобрить заявку
            $financingRequest->approve($user);
            $this->entityManager->flush();

            // Создать транзакцию для перевода денег
            $transaction = $this->transactionService->transfer(
                from: $financingRequest->getFactorAccount(),
                to: $financingRequest->getSupplierAccount(),
                amount: $financingRequest->getAmount(),
                type: \App\Banking\Entity\TransactionType::TRANSFER,
                idempotencyKey: 'financing_request_' . $financingRequest->getId()->toRfc4122(),
                initiatedBy: $user,
                description: 'Финансирование по заявке #' . $financingRequest->getId()->toRfc4122(),
            );

            // Связать транзакцию с заявкой
            $financingRequest->markAsFunded($transaction);
            $this->entityManager->flush();

            $this->entityManager->commit();

            $this->logger->info('Financing request approved and funded', [
                'request_id' => $financingRequest->getId()->toRfc4122(),
                'transaction_id' => $transaction->getId()->toRfc4122(),
                'approved_by' => $user->getEmail(),
            ]);

            return new JsonResponse($this->formatRequest($financingRequest));
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to approve financing request', [
                'request_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(
                ['error' => 'Failed to approve: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Отклонить заявку (только фактор)
     */
    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    #[IsGranted('FINANCING_REQUEST_APPROVE')]
    public function reject(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Проверяем, что пользователь из компании-фактора
        if ($user->getCompany()->getType() !== 'factor') {
            return new JsonResponse(
                ['error' => 'Только фактор может отклонять заявки'],
                Response::HTTP_FORBIDDEN
            );
        }

        $financingRequest = $this->repository->find($id);
        if (!$financingRequest) {
            return new JsonResponse(['error' => 'Request not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$financingRequest->canBeApprovedBy($user)) {
            return new JsonResponse(
                ['error' => 'You cannot reject this request'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? 'Отклонено без указания причины';

        try {
            $financingRequest->reject($user, $reason);
            $this->entityManager->flush();

            $this->logger->info('Financing request rejected', [
                'request_id' => $financingRequest->getId()->toRfc4122(),
                'rejected_by' => $user->getEmail(),
                'reason' => $reason,
            ]);

            return new JsonResponse($this->formatRequest($financingRequest));
        } catch (\Exception $e) {
            $this->logger->error('Failed to reject financing request', [
                'request_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(
                ['error' => 'Failed to reject: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Форматировать заявку для API
     */
    private function formatRequest(FinancingRequest $request): array
    {
        return [
            'id' => $request->getId()->toRfc4122(),
            'amountMinorUnits' => $request->getAmountMinorUnits(),
            'currency' => $request->getCurrency(),
            'status' => $request->getStatus()->value,
            'statusLabel' => $request->getStatus()->getLabel(),
            'supplierCompany' => [
                'id' => $request->getSupplierCompany()->getId()->toRfc4122(),
                'name' => $request->getSupplierCompany()->getName(),
            ],
            'debtorCompany' => [
                'id' => $request->getDebtorCompany()->getId()->toRfc4122(),
                'name' => $request->getDebtorCompany()->getName(),
            ],
            'factorAccount' => [
                'id' => $request->getFactorAccount()->getId()->toRfc4122(),
                'name' => $request->getFactorAccount()->getName(),
            ],
            'supplierAccount' => [
                'id' => $request->getSupplierAccount()->getId()->toRfc4122(),
                'name' => $request->getSupplierAccount()->getName(),
            ],
            'createdBy' => [
                'id' => $request->getCreatedBy()->getId()->toRfc4122(),
                'email' => $request->getCreatedBy()->getEmail(),
            ],
            'approvedBy' => $request->getApprovedBy() ? [
                'id' => $request->getApprovedBy()->getId()->toRfc4122(),
                'email' => $request->getApprovedBy()->getEmail(),
            ] : null,
            'transaction' => $request->getTransaction() ? [
                'id' => $request->getTransaction()->getId()->toRfc4122(),
                'status' => $request->getTransaction()->getStatus()->value,
            ] : null,
            'description' => $request->getDescription(),
            'rejectionReason' => $request->getRejectionReason(),
            'createdAt' => $request->getCreatedAt()->format('c'),
            'approvedAt' => $request->getApprovedAt()?->format('c'),
            'fundedAt' => $request->getFundedAt()?->format('c'),
            'updatedAt' => $request->getUpdatedAt()->format('c'),
        ];
    }
}
