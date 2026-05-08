<?php

declare(strict_types=1);

namespace App\Controller;

use App\Identity\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companyRepository
    ) {
    }

    #[Route('/register.html', name: 'app_register', methods: ['GET'])]
    public function register(): Response
    {
        $companies = $this->companyRepository->findAll();

        return $this->render('registration/register.html.twig', [
            'companies' => $companies,
        ]);
    }
}
