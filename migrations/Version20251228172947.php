<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228172947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bank_accounts DROP CONSTRAINT fk_fb88842b979b1ad6');
        $this->addSql('DROP TABLE bank_accounts');
        $this->addSql('ALTER TABLE transactions ADD processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD failure_reason TEXT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN transactions.processed_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE bank_accounts (id UUID NOT NULL, company_id UUID NOT NULL, account_number VARCHAR(20) NOT NULL, account_name VARCHAR(100) NOT NULL, balance NUMERIC(15, 2) NOT NULL, currency VARCHAR(3) NOT NULL, bik VARCHAR(9) NOT NULL, bank_name VARCHAR(100) NOT NULL, correspondent_account VARCHAR(20) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_bank_accounts_active ON bank_accounts (is_active)');
        $this->addSql('CREATE INDEX idx_bank_accounts_company ON bank_accounts (company_id)');
        $this->addSql('CREATE INDEX idx_bank_accounts_number ON bank_accounts (account_number)');
        $this->addSql('CREATE UNIQUE INDEX uniq_fb88842bb1a4d127 ON bank_accounts (account_number)');
        $this->addSql('COMMENT ON COLUMN bank_accounts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_accounts.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_accounts.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bank_accounts.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE bank_accounts ADD CONSTRAINT fk_fb88842b979b1ad6 FOREIGN KEY (company_id) REFERENCES companies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions DROP processed_at');
        $this->addSql('ALTER TABLE transactions DROP failure_reason');
    }
}
