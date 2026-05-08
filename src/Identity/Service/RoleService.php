<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\Permission;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Repository\PermissionRepository;
use App\Identity\Repository\RoleRepository;
use Symfony\Component\Uid\Uuid;

class RoleService
{
    public function __construct(
        private readonly RoleRepository $roleRepository,
        private readonly PermissionRepository $permissionRepository
    ) {
    }

    public function createRole(string $name, ?string $description = null): Role
    {
        $role = new Role($name, $description);
        $this->roleRepository->save($role);

        return $role;
    }

    public function updateRole(Uuid $roleId, string $name, ?string $description = null): Role
    {
        $role = $this->roleRepository->findById($roleId);

        if (!$role) {
            throw new \RuntimeException("Role not found");
        }

        $role->setName($name);
        $role->setDescription($description);
        $this->roleRepository->save($role);

        return $role;
    }

    public function deleteRole(Uuid $roleId): void
    {
        $role = $this->roleRepository->findById($roleId);

        if (!$role) {
            throw new \RuntimeException("Role not found");
        }

        $this->roleRepository->remove($role);
    }

    public function assignPermissionToRole(Uuid $roleId, Uuid $permissionId): Role
    {
        $role = $this->roleRepository->findById($roleId);
        $permission = $this->permissionRepository->findById($permissionId);

        if (!$role) {
            throw new \RuntimeException("Role not found");
        }

        if (!$permission) {
            throw new \RuntimeException("Permission not found");
        }

        $role->addPermission($permission);
        $this->roleRepository->save($role);

        return $role;
    }

    public function removePermissionFromRole(Uuid $roleId, Uuid $permissionId): Role
    {
        $role = $this->roleRepository->findById($roleId);
        $permission = $this->permissionRepository->findById($permissionId);

        if (!$role) {
            throw new \RuntimeException("Role not found");
        }

        if (!$permission) {
            throw new \RuntimeException("Permission not found");
        }

        $role->removePermission($permission);
        $this->roleRepository->save($role);

        return $role;
    }

    public function assignRoleToUser(User $user, Uuid $roleId): User
    {
        $role = $this->roleRepository->findById($roleId);

        if (!$role) {
            throw new \RuntimeException("Role not found");
        }

        $user->addRole($role);

        return $user;
    }

    public function removeRoleFromUser(User $user, Uuid $roleId): User
    {
        $role = $this->roleRepository->findById($roleId);

        if (!$role) {
            throw new \RuntimeException("Role not found");
        }

        $user->removeRole($role);

        return $user;
    }

    /**
     * Get role by name or create if not exists
     */
    public function getOrCreateRole(string $name, ?string $description = null): Role
    {
        $role = $this->roleRepository->findByName($name);

        if (!$role) {
            $role = $this->createRole($name, $description);
        }

        return $role;
    }

    /**
     * @return Role[]
     */
    public function getAllRoles(): array
    {
        return $this->roleRepository->findAllOrderedByName();
    }
}
