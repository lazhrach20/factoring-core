<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251227233502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Add company_id as nullable
        $this->addSql('ALTER TABLE banking_accounts ADD company_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN banking_accounts.company_id IS \'(DC2Type:uuid)\'');

        // Step 2: Link existing accounts to companies based on owner_id
        // Get company_id from users table where user owns the account
        $this->addSql('
            UPDATE banking_accounts ba
            SET company_id = u.company_id
            FROM users u
            WHERE ba.owner_id = u.id
        ');

        // Step 3: For any accounts without a company (shouldn\'t happen but just in case),
        // assign them to the first factor company
        $this->addSql('
            UPDATE banking_accounts
            SET company_id = (SELECT id FROM companies WHERE type = \'factor\' LIMIT 1)
            WHERE company_id IS NULL
        ');

        // Step 4: Make company_id NOT NULL now that all rows have a value
        $this->addSql('ALTER TABLE banking_accounts ALTER COLUMN company_id SET NOT NULL');

        // Step 5: Add foreign key constraint and index
        $this->addSql('ALTER TABLE banking_accounts ADD CONSTRAINT FK_8180F922979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_banking_accounts_company_id ON banking_accounts (company_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE banking_accounts DROP CONSTRAINT FK_8180F922979B1AD6');
        $this->addSql('DROP INDEX idx_banking_accounts_company_id');
        $this->addSql('ALTER TABLE banking_accounts DROP company_id');
    }
}
