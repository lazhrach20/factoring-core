<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Entity\Permission;
use App\Identity\Repository\PermissionRepository;
use Symfony\Component\Uid\Uuid;

class PermissionService
{
    public function __construct(
        private readonly PermissionRepository $permissionRepository
    ) {
    }

    public function createPermission(
        string $name,
        ?string $description = null,
        ?string $category = null
    ): Permission {
        $permission = new Permission($name, $description, $category);
        $this->permissionRepository->save($permission);

        return $permission;
    }

    public function updatePermission(
        Uuid $permissionId,
        string $name,
        ?string $description = null,
        ?string $category = null
    ): Permission {
        $permission = $this->permissionRepository->findById($permissionId);

        if (!$permission) {
            throw new \RuntimeException("Permission not found");
        }

        $permission->setName($name);
        $permission->setDescription($description);
        $permission->setCategory($category);
        $this->permissionRepository->save($permission);

        return $permission;
    }

    public function deletePermission(Uuid $permissionId): void
    {
        $permission = $this->permissionRepository->findById($permissionId);

        if (!$permission) {
            throw new \RuntimeException("Permission not found");
        }

        $this->permissionRepository->remove($permission);
    }

    /**
     * Get permission by name or create if not exists
     */
    public function getOrCreatePermission(
        string $name,
        ?string $description = null,
        ?string $category = null
    ): Permission {
        $permission = $this->permissionRepository->findByName($name);

        if (!$permission) {
            $permission = $this->createPermission($name, $description, $category);
        }

        return $permission;
    }

    /**
     * @return Permission[]
     */
    public function getAllPermissions(): array
    {
        return $this->permissionRepository->findAllOrderedByName();
    }

    /**
     * @return Permission[]
     */
    public function getPermissionsByCategory(?string $category): array
    {
        return $this->permissionRepository->findByCategory($category);
    }
}
