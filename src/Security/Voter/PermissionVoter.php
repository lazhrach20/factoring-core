<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Identity\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter для проверки permissions пользователя
 *
 * Используется для проверки специфичных прав типа:
 * - CREATE_USER, EDIT_USER, DELETE_USER
 * - CREATE_TRANSACTION, APPROVE_TRANSACTION
 * и т.д.
 */
class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Этот voter поддерживает проверку любых permission-атрибутов
        // Примеры: CREATE_USER, EDIT_COMPANY, APPROVE_TRANSACTION
        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Проверяем наличие permission через метод User::hasPermission()
        return $user->hasPermission($attribute);
    }
}
