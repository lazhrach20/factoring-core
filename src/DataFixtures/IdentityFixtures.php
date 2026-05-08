<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Identity\Entity\Company;
use App\Identity\Entity\CompanyType;
use App\Identity\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Fixtures for Identity module - creates test companies and users
 */
class IdentityFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // ==================== Companies ====================

        // 1. Factoring Company (Platform Owner)
        $factorCompany = new Company(
            Uuid::v4(),
            'ООО "ФинансФактор"',
            '7701234567',
            CompanyType::FACTOR->value,
            'г. Москва, ул. Тверская, д. 10'
        );
        $factorCompany->setKpp('770101001');
        $factorCompany->setOgrn('1027700000001');
        $factorCompany->setBankAccount('40702810100000000001');
        $factorCompany->setBankBik('044525225');
        $factorCompany->setCorrespondentAccount('30101810400000000225');
        $manager->persist($factorCompany);

        // 2. Supplier Company (Client)
        $supplierCompany = new Company(
            Uuid::v4(),
            'ООО "Поставщик Товаров"',
            '7702345678',
            CompanyType::SUPPLIER->value,
            'г. Москва, ул. Ленина, д. 25, офис 301'
        );
        $supplierCompany->setKpp('770201001');
        $supplierCompany->setOgrn('1027700000002');
        $supplierCompany->setBankAccount('40702810200000000002');
        $supplierCompany->setBankBik('044525225');
        $manager->persist($supplierCompany);

        // 3. Another Supplier
        $supplier2Company = new Company(
            Uuid::v4(),
            'ИП Иванов Иван Иванович',
            '770345678901',  // ИНН для ИП - 12 цифр
            CompanyType::SUPPLIER->value,
            'г. Санкт-Петербург, Невский проспект, д. 100'
        );
        $supplier2Company->setOgrn('304770300000003');
        $supplier2Company->setBankAccount('40802810300000000003');
        $supplier2Company->setBankBik('044030653');
        $manager->persist($supplier2Company);

        // 4. Debtor Company
        $debtorCompany = new Company(
            Uuid::v4(),
            'ООО "Покупатель Плюс"',
            '7703456789',
            CompanyType::DEBTOR->value,
            'г. Москва, ул. Арбат, д. 5'
        );
        $debtorCompany->setKpp('770301001');
        $debtorCompany->setOgrn('1027700000004');
        $manager->persist($debtorCompany);

        $manager->flush();

        // ==================== Users ====================

        // 1. Admin of Factoring Company
        $admin = new User(
            Uuid::v4(),
            'admin@finansfactor.ru',
            $this->passwordHasher->hashPassword(
                new User(Uuid::v4(), 'temp', 'temp', 'Temp', 'Temp', $factorCompany),
                'admin123'
            ),
            'Алексей',
            'Петров',
            $factorCompany
        );
        $admin->setPhone('+79161234567');
        $manager->persist($admin);

        // 2. Accountant of Factoring Company
        $accountant = new User(
            Uuid::v4(),
            'accountant@finansfactor.ru',
            $this->passwordHasher->hashPassword(
                new User(Uuid::v4(), 'temp', 'temp', 'Temp', 'Temp', $factorCompany),
                'accountant123'
            ),
            'Мария',
            'Смирнова',
            $factorCompany
        );
        $accountant->setPhone('+79161234568');
        $manager->persist($accountant);

        // 3. Risk Manager of Factoring Company
        $riskManager = new User(
            Uuid::v4(),
            'risk@finansfactor.ru',
            $this->passwordHasher->hashPassword(
                new User(Uuid::v4(), 'temp', 'temp', 'Temp', 'Temp', $factorCompany),
                'risk123'
            ),
            'Дмитрий',
            'Козлов',
            $factorCompany
        );
        $manager->persist($riskManager);

        // 4. Supplier Owner
        $supplierOwner = new User(
            Uuid::v4(),
            'owner@supplier.ru',
            $this->passwordHasher->hashPassword(
                new User(Uuid::v4(), 'temp', 'temp', 'Temp', 'Temp', $supplierCompany),
                'supplier123'
            ),
            'Сергей',
            'Иванов',
            $supplierCompany
        );
        $supplierOwner->setPhone('+79161234569');
        $manager->persist($supplierOwner);

        // 5. Supplier Finance Director
        $supplierFinance = new User(
            Uuid::v4(),
            'finance@supplier.ru',
            $this->passwordHasher->hashPassword(
                new User(Uuid::v4(), 'temp', 'temp', 'Temp', 'Temp', $supplierCompany),
                'finance123'
            ),
            'Елена',
            'Соколова',
            $supplierCompany
        );
        $manager->persist($supplierFinance);

        // 6. Second Supplier Owner (IP)
        $supplier2Owner = new User(
            Uuid::v4(),
            'ivan@ivanov.ru',
            $this->passwordHasher->hashPassword(
                new User(Uuid::v4(), 'temp', 'temp', 'Temp', 'Temp', $supplier2Company),
                'ivan123'
            ),
            'Иван',
            'Иванов',
            $supplier2Company
        );
        $supplier2Owner->setPhone('+79211234567');
        $manager->persist($supplier2Owner);

        // 7. Debtor Finance Director
        $debtorFinance = new User(
            Uuid::v4(),
            'finance@debtor.ru',
            $this->passwordHasher->hashPassword(
                new User(Uuid::v4(), 'temp', 'temp', 'Temp', 'Temp', $debtorCompany),
                'debtor123'
            ),
            'Ольга',
            'Новикова',
            $debtorCompany
        );
        $manager->persist($debtorFinance);

        $manager->flush();
    }
}
