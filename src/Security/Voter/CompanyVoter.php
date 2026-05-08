<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Identity\Entity\Company;
use App\Identity\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter для проверки доступа к ресурсам компании (мультитенантность)
 *
 * Обеспечивает что пользователи видят только данные своей компании
 */
class CompanyVoter extends Voter
{
    public const VIEW = 'COMPANY_VIEW';
    public const EDIT = 'COMPANY_EDIT';
    public const ACCESS = 'COMPANY_ACCESS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::ACCESS])) {
            return false;
        }

        // Subject должен иметь метод getCompany() или getCompanyId()
        if (!is_object($subject)) {
            return false;
        }

        return method_exists($subject, 'getCompany') ||
               method_exists($subject, 'getCompanyId');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Администраторы факторинговой компании видят все
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // Получаем компанию из subject
        $subjectCompany = $this->getSubjectCompany($subject);

        if (!$subjectCompany) {
            return false;
        }

        // Проверяем что ресурс принадлежит компании пользователя
        return $user->getCompanyId()->equals($subjectCompany->getId());
    }

    private function getSubjectCompany(object $subject): ?Company
    {
        if (method_exists($subject, 'getCompany')) {
            return $subject->getCompany();
        }

        return null;
    }
}
