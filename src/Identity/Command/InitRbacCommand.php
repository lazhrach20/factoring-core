<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Service\PermissionService;
use App\Identity\Service\RoleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rbac:init',
    description: 'Initialize RBAC system with default roles and permissions',
)]
class InitRbacCommand extends Command
{
    public function __construct(
        private readonly RoleService $roleService,
        private readonly PermissionService $permissionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Initializing RBAC System');

        // Create Permissions
        $io->section('Creating Permissions');

        $permissions = [
            // User Management
            ['name' => 'CREATE_USER', 'description' => 'Создание пользователей', 'category' => 'user'],
            ['name' => 'EDIT_USER', 'description' => 'Редактирование пользователей', 'category' => 'user'],
            ['name' => 'DELETE_USER', 'description' => 'Удаление пользователей', 'category' => 'user'],
            ['name' => 'VIEW_USERS', 'description' => 'Просмотр списка пользователей', 'category' => 'user'],
            ['name' => 'BLOCK_USER', 'description' => 'Блокировка пользователей', 'category' => 'user'],

            // Company Management
            ['name' => 'CREATE_COMPANY', 'description' => 'Создание компаний', 'category' => 'company'],
            ['name' => 'EDIT_COMPANY', 'description' => 'Редактирование компаний', 'category' => 'company'],
            ['name' => 'DELETE_COMPANY', 'description' => 'Удаление компаний', 'category' => 'company'],
            ['name' => 'VIEW_COMPANIES', 'description' => 'Просмотр списка компаний', 'category' => 'company'],

            // Role Management
            ['name' => 'MANAGE_ROLES', 'description' => 'Управление ролями и правами', 'category' => 'rbac'],
            ['name' => 'ASSIGN_ROLES', 'description' => 'Назначение ролей пользователям', 'category' => 'rbac'],

            // Financial Operations (for future stages)
            ['name' => 'VIEW_TRANSACTIONS', 'description' => 'Просмотр транзакций', 'category' => 'finance'],
            ['name' => 'CREATE_TRANSACTION', 'description' => 'Создание транзакций', 'category' => 'finance'],
            ['name' => 'APPROVE_TRANSACTION', 'description' => 'Одобрение транзакций', 'category' => 'finance'],
            ['name' => 'CANCEL_TRANSACTION', 'description' => 'Отмена транзакций', 'category' => 'finance'],

            // Reports
            ['name' => 'VIEW_REPORTS', 'description' => 'Просмотр отчетов', 'category' => 'reports'],
            ['name' => 'EXPORT_REPORTS', 'description' => 'Экспорт отчетов', 'category' => 'reports'],
        ];

        $createdPermissions = [];
        foreach ($permissions as $permData) {
            $permission = $this->permissionService->getOrCreatePermission(
                $permData['name'],
                $permData['description'],
                $permData['category']
            );
            $createdPermissions[$permData['name']] = $permission;
            $io->writeln("  ✓ {$permData['name']}");
        }

        // Create Roles
        $io->section('Creating Roles');

        // ROLE_ADMIN - Full access
        $roleAdmin = $this->roleService->getOrCreateRole(
            'ROLE_ADMIN',
            'Администратор системы - полный доступ'
        );
        foreach ($createdPermissions as $permission) {
            $roleAdmin->addPermission($permission);
        }
        $io->writeln("  ✓ ROLE_ADMIN (all permissions)");

        // ROLE_MANAGER - User and company management
        $roleManager = $this->roleService->getOrCreateRole(
            'ROLE_MANAGER',
            'Менеджер - управление пользователями и компаниями'
        );
        $managerPermissions = [
            'VIEW_USERS', 'CREATE_USER', 'EDIT_USER',
            'VIEW_COMPANIES', 'EDIT_COMPANY',
            'VIEW_TRANSACTIONS', 'VIEW_REPORTS'
        ];
        foreach ($managerPermissions as $permName) {
            if (isset($createdPermissions[$permName])) {
                $roleManager->addPermission($createdPermissions[$permName]);
            }
        }
        $io->writeln("  ✓ ROLE_MANAGER");

        // ROLE_ACCOUNTANT - Financial operations
        $roleAccountant = $this->roleService->getOrCreateRole(
            'ROLE_ACCOUNTANT',
            'Бухгалтер - работа с финансовыми операциями'
        );
        $accountantPermissions = [
            'VIEW_TRANSACTIONS', 'CREATE_TRANSACTION', 'APPROVE_TRANSACTION',
            'VIEW_REPORTS', 'EXPORT_REPORTS'
        ];
        foreach ($accountantPermissions as $permName) {
            if (isset($createdPermissions[$permName])) {
                $roleAccountant->addPermission($createdPermissions[$permName]);
            }
        }
        $io->writeln("  ✓ ROLE_ACCOUNTANT");

        // ROLE_USER - Basic user (no special permissions, only ROLE_USER from UserInterface)
        $roleUser = $this->roleService->getOrCreateRole(
            'ROLE_USER',
            'Базовый пользователь'
        );
        $io->writeln("  ✓ ROLE_USER");

        $io->success('RBAC system initialized successfully!');
        $io->note([
            'Created ' . count($createdPermissions) . ' permissions',
            'Created 4 roles: ADMIN, MANAGER, ACCOUNTANT, USER',
            'Use "app:user:assign-role" command to assign roles to users'
        ]);

        return Command::SUCCESS;
    }
}
