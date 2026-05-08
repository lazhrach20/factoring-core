<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Identity\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ApiKeyVoter extends Voter
{
    public const MANAGE = 'API_KEY_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE => $this->canManageApiKeys($user),
            default => false,
        };
    }

    private function canManageApiKeys(User $user): bool
    {
        $company = $user->getCompany();
        $companyType = $company?->getType();

        // Factor company: только админы (для тестирования)
        if ($companyType === 'factor') {
            return in_array('ROLE_ADMIN', $user->getRoles(), true);
        }

        // Supplier company: любой пользователь компании-поставщика
        // (они сами управляют своими интеграциями с 1С/ERP)
        if ($companyType === 'supplier') {
            return true;
        }

        // Debtor company: любой пользователь компании-дебитора
        // (они тоже управляют своими интеграциями)
        if ($companyType === 'debtor') {
            return true;
        }

        return false;
    }
}
