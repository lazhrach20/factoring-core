<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251227181958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bank_accounts (id UUID NOT NULL, company_id UUID NOT NULL, account_number VARCHAR(20) NOT NULL, account_name VARCHAR(100) NOT NULL, balance NUMERIC(15, 2) NOT NULL, currency VARCHAR(3) NOT NULL, bik VARCHAR(9) NOT NULL, bank_name VARCHAR(100) NOT NULL, correspondent_account VARCHAR(20) DEFAULT NULL, is_active BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FB88842BB1A4D127 ON bank_accounts (account_number)');
        $this->addSql('CREATE INDEX idx_bank_accounts_company ON bank_accounts (company_id)');
        $this->addSql('CREATE INDEX idx_bank_accounts_number ON bank_accounts (account_number)');
        $this->addSql('CREATE INDEX idx_bank_accounts_active ON bank_accounts (is_active)');
        $this->addSql('COMMENT ON COLUMN bank_accounts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_accounts.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_accounts.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bank_accounts.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE transactions (id UUID NOT NULL, from_account_id UUID DEFAULT NULL, to_account_id UUID DEFAULT NULL, created_by_id UUID DEFAULT NULL, approved_by_id UUID DEFAULT NULL, amount NUMERIC(15, 2) NOT NULL, currency VARCHAR(3) NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, description TEXT DEFAULT NULL, purpose TEXT DEFAULT NULL, reference_number VARCHAR(50) NOT NULL, approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, failure_reason TEXT DEFAULT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAA81A4C8BF1AE50 ON transactions (reference_number)');
        $this->addSql('CREATE INDEX IDX_EAA81A4CB03A8386 ON transactions (created_by_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4C2D234F6A ON transactions (approved_by_id)');
        $this->addSql('CREATE INDEX idx_transactions_from ON transactions (from_account_id)');
        $this->addSql('CREATE INDEX idx_transactions_to ON transactions (to_account_id)');
        $this->addSql('CREATE INDEX idx_transactions_status ON transactions (status)');
        $this->addSql('CREATE INDEX idx_transactions_type ON transactions (type)');
        $this->addSql('CREATE INDEX idx_transactions_created ON transactions (created_at)');
        $this->addSql('COMMENT ON COLUMN transactions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.from_account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.to_account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.created_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.approved_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.approved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN transactions.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN transactions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN transactions.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE bank_accounts ADD CONSTRAINT FK_FB88842B979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CB0CF99BD FOREIGN KEY (from_account_id) REFERENCES bank_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CBC58BDC7 FOREIGN KEY (to_account_id) REFERENCES bank_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C2D234F6A FOREIGN KEY (approved_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE bank_accounts DROP CONSTRAINT FK_FB88842B979B1AD6');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4CB0CF99BD');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4CBC58BDC7');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4CB03A8386');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4C2D234F6A');
        $this->addSql('DROP TABLE bank_accounts');
        $this->addSql('DROP TABLE transactions');
    }
}
