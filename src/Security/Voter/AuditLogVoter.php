<?php

namespace App\Security\Voter;

use App\Identity\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AuditLogVoter extends Voter
{
    public const VIEW = 'AUDIT_LOG_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canViewAuditLogs($user),
            default => false,
        };
    }

    private function canViewAuditLogs(User $user): bool
    {
        // Only users with specific roles can view audit logs
        // ROLE_ADMIN - full access to all audit logs
        // ROLE_AUDIT_VIEWER - can view audit logs (специальная роль для аудиторов)

        $roles = $user->getRoles();

        // Check if user has ROLE_ADMIN or ROLE_AUDIT_VIEWER
        return in_array('ROLE_ADMIN', $roles, true)
            || in_array('ROLE_AUDIT_VIEWER', $roles, true);
    }
}
