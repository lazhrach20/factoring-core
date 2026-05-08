<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Repository\RoleRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\RoleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:assign-role',
    description: 'Assign role to user',
)]
class AssignRoleCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly RoleService $roleService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('role', InputArgument::REQUIRED, 'Role name (e.g., ROLE_ADMIN)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $roleName = $input->getArgument('role');

        // Find user
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            $io->error("User with email '{$email}' not found");
            return Command::FAILURE;
        }

        // Find role
        $role = $this->roleRepository->findByName($roleName);
        if (!$role) {
            $io->error("Role '{$roleName}' not found");
            $io->note('Available roles: ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_USER');
            $io->note('Run "app:rbac:init" to initialize roles');
            return Command::FAILURE;
        }

        // Check if user already has this role
        if ($user->hasRole($roleName)) {
            $io->warning("User '{$email}' already has role '{$roleName}'");
            return Command::SUCCESS;
        }

        // Assign role
        $this->roleService->assignRoleToUser($user, $role->getId());
        $this->entityManager->flush();

        $io->success("Role '{$roleName}' assigned to user '{$email}'");

        // Show user's current roles
        $userRoles = array_map(fn($r) => $r->getName(), $user->getUserRoles()->toArray());
        $io->note('User roles: ' . implode(', ', $userRoles));

        return Command::SUCCESS;
    }
}
