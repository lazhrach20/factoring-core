<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\Company;
use App\Identity\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API Controller for Company management
 * Only accessible to factor company admins
 */
#[Route('/api/companies', name: 'api_companies_')]
#[IsGranted('ROLE_ADMIN')] // Только администраторы
class CompanyController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
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
                ['error' => 'Доступ запрещен. Только фактор может управлять компаниями.'],
                Response::HTTP_FORBIDDEN
            );
        }

        return null;
    }

    /**
     * Get all companies
     * GET /api/companies
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        $type = $request->query->get('type'); // filter by type
        $status = $request->query->get('status'); // filter by status

        if ($type && $status) {
            $companies = $this->companyRepository->findBy(['type' => $type, 'status' => $status]);
        } elseif ($type) {
            $companies = $this->companyRepository->findBy(['type' => $type]);
        } elseif ($status) {
            $companies = $this->companyRepository->findBy(['status' => $status]);
        } else {
            $companies = $this->companyRepository->findAll();
        }

        $data = array_map(fn(Company $company) => $this->formatCompany($company), $companies);

        return $this->json($data);
    }

    /**
     * Get single company
     * GET /api/companies/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        $company = $this->companyRepository->find($id);

        if (!$company) {
            return $this->json(['error' => 'Компания не найдена'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatCompany($company));
    }

    /**
     * Create new company
     * POST /api/companies
     *
     * Request body:
     * {
     *   "name": "ООО Новая компания",
     *   "inn": "1234567890",
     *   "kpp": "123456789",
     *   "ogrn": "1234567890123",
     *   "type": "supplier",
     *   "legalAddress": "Москва, ул. Ленина, 1",
     *   "postalAddress": "Москва, ул. Ленина, 1",
     *   "bankAccount": "40702810100000000001",
     *   "bankBik": "044525225",
     *   "correspondentAccount": "30101810400000000225"
     * }
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Некорректные данные'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        $requiredFields = ['name', 'inn', 'type'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => "Поле '$field' обязательно"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validate type
        $validTypes = ['factor', 'supplier', 'debtor'];
        if (!in_array($data['type'], $validTypes)) {
            return $this->json(['error' => 'Некорректный тип компании'], Response::HTTP_BAD_REQUEST);
        }

        // Check if company with this INN already exists
        $existing = $this->companyRepository->findOneBy(['inn' => $data['inn']]);
        if ($existing) {
            return $this->json(['error' => 'Компания с таким ИНН уже существует'], Response::HTTP_CONFLICT);
        }

        try {
            $company = new Company(
                Uuid::v4(),
                $data['name'],
                $data['inn'],
                $data['type'],
                $data['legalAddress'] ?? ''
            );

            // Set optional fields
            if (isset($data['kpp'])) {
                $company->setKpp($data['kpp']);
            }
            if (isset($data['ogrn'])) {
                $company->setOgrn($data['ogrn']);
            }
            if (isset($data['postalAddress'])) {
                $company->setPostalAddress($data['postalAddress']);
            }
            if (isset($data['bankAccount'])) {
                $company->setBankAccount($data['bankAccount']);
            }
            if (isset($data['bankBik'])) {
                $company->setBankBik($data['bankBik']);
            }
            if (isset($data['correspondentAccount'])) {
                $company->setCorrespondentAccount($data['correspondentAccount']);
            }

            // Validate entity
            $errors = $this->validator->validate($company);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => 'Ошибка валидации', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($company);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Компания успешно создана',
                'company' => $this->formatCompany($company)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка создания компании: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update company
     * PUT /api/companies/{id}
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        $company = $this->companyRepository->find($id);

        if (!$company) {
            return $this->json(['error' => 'Компания не найдена'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Некорректные данные'], Response::HTTP_BAD_REQUEST);
        }

        try {
            if (isset($data['name'])) {
                $company->setName($data['name']);
            }
            if (isset($data['inn'])) {
                // Check for duplicate INN
                $existing = $this->companyRepository->findOneBy(['inn' => $data['inn']]);
                if ($existing && $existing->getId()->toRfc4122() !== $id) {
                    return $this->json(['error' => 'Компания с таким ИНН уже существует'], Response::HTTP_CONFLICT);
                }
                $company->setInn($data['inn']);
            }
            if (isset($data['kpp'])) {
                $company->setKpp($data['kpp']);
            }
            if (isset($data['ogrn'])) {
                $company->setOgrn($data['ogrn']);
            }
            if (isset($data['legalAddress'])) {
                $company->setLegalAddress($data['legalAddress']);
            }
            if (isset($data['postalAddress'])) {
                $company->setPostalAddress($data['postalAddress']);
            }
            if (isset($data['bankAccount'])) {
                $company->setBankAccount($data['bankAccount']);
            }
            if (isset($data['bankBik'])) {
                $company->setBankBik($data['bankBik']);
            }
            if (isset($data['correspondentAccount'])) {
                $company->setCorrespondentAccount($data['correspondentAccount']);
            }
            if (isset($data['status'])) {
                $company->setStatus($data['status']);
            }

            $this->entityManager->flush();

            return $this->json([
                'message' => 'Компания успешно обновлена',
                'company' => $this->formatCompany($company)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Ошибка обновления компании: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Deactivate company
     * POST /api/companies/{id}/deactivate
     */
    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(string $id): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        $company = $this->companyRepository->find($id);

        if (!$company) {
            return $this->json(['error' => 'Компания не найдена'], Response::HTTP_NOT_FOUND);
        }

        $company->setStatus('inactive');
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Компания деактивирована',
            'company' => $this->formatCompany($company)
        ]);
    }

    /**
     * Activate company
     * POST /api/companies/{id}/activate
     */
    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    public function activate(string $id): JsonResponse
    {
        // Проверяем доступ
        if ($error = $this->checkFactorAccess()) {
            return $error;
        }

        $company = $this->companyRepository->find($id);

        if (!$company) {
            return $this->json(['error' => 'Компания не найдена'], Response::HTTP_NOT_FOUND);
        }

        $company->setStatus('active');
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Компания активирована',
            'company' => $this->formatCompany($company)
        ]);
    }

    /**
     * Format company for JSON response
     */
    private function formatCompany(Company $company): array
    {
        return [
            'id' => $company->getId()->toRfc4122(),
            'name' => $company->getName(),
            'inn' => $company->getInn(),
            'kpp' => $company->getKpp(),
            'ogrn' => $company->getOgrn(),
            'type' => $company->getType(),
            'status' => $company->getStatus(),
            'legalAddress' => $company->getLegalAddress(),
            'postalAddress' => $company->getPostalAddress(),
            'bankAccount' => $company->getBankAccount(),
            'bankBik' => $company->getBankBik(),
            'correspondentAccount' => $company->getCorrespondentAccount(),
            'createdAt' => $company->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $company->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
