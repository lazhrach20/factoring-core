<?php

namespace App\Security\Voter;

use App\Banking\Entity\Account;
use App\Identity\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AccountVoter extends Voter
{
    public const VIEW = 'ACCOUNT_VIEW';
    public const EDIT = 'ACCOUNT_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof Account;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Account $account */
        $account = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($account, $user),
            self::EDIT => $this->canEdit($account, $user),
            default => false,
        };
    }

    private function canView(Account $account, User $user): bool
    {
        // User can view account if:
        // 1. Account belongs to user's company
        // 2. OR user is admin of factor company (can see all accounts)

        $userCompany = $user->getCompany();
        $accountCompany = $account->getCompany();

        // Same company - can view
        if ($userCompany->getId()->equals($accountCompany->getId())) {
            return true;
        }

        // Admin from factor company can view all accounts
        if ($userCompany->getType() === 'factor' && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return false;
    }

    private function canEdit(Account $account, User $user): bool
    {
        // User can edit account if:
        // 1. Account belongs to user's company AND user has appropriate role

        $userCompany = $user->getCompany();
        $accountCompany = $account->getCompany();

        // Different company - cannot edit
        if (!$userCompany->getId()->equals($accountCompany->getId())) {
            return false;
        }

        // Check roles
        $roles = $user->getRoles();

        // Admins and treasurers can edit
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_TREASURER', $roles, true)) {
            return true;
        }

        return false;
    }
}
