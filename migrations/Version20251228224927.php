<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228224927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE messenger_messages_id_seq CASCADE');
        $this->addSql('CREATE TABLE financing_requests (id UUID NOT NULL, supplier_company_id UUID NOT NULL, debtor_company_id UUID NOT NULL, factor_account_id UUID NOT NULL, supplier_account_id UUID NOT NULL, created_by_id UUID NOT NULL, approved_by_id UUID DEFAULT NULL, transaction_id UUID DEFAULT NULL, amount_minor_units BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(20) NOT NULL, description TEXT DEFAULT NULL, rejection_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, funded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BDA8926458C21047 ON financing_requests (factor_account_id)');
        $this->addSql('CREATE INDEX IDX_BDA89264FA9A9253 ON financing_requests (supplier_account_id)');
        $this->addSql('CREATE INDEX IDX_BDA89264B03A8386 ON financing_requests (created_by_id)');
        $this->addSql('CREATE INDEX IDX_BDA892642D234F6A ON financing_requests (approved_by_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BDA892642FC0CB0F ON financing_requests (transaction_id)');
        $this->addSql('CREATE INDEX idx_financing_requests_supplier ON financing_requests (supplier_company_id)');
        $this->addSql('CREATE INDEX idx_financing_requests_debtor ON financing_requests (debtor_company_id)');
        $this->addSql('CREATE INDEX idx_financing_requests_status ON financing_requests (status)');
        $this->addSql('CREATE INDEX idx_financing_requests_created_at ON financing_requests (created_at)');
        $this->addSql('COMMENT ON COLUMN financing_requests.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.supplier_company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.debtor_company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.factor_account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.supplier_account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.created_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.approved_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.transaction_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.approved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.funded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN financing_requests.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE financing_requests ADD CONSTRAINT FK_BDA89264F66AD73F FOREIGN KEY (supplier_company_id) REFERENCES companies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE financing_requests ADD CONSTRAINT FK_BDA89264EDC45283 FOREIGN KEY (debtor_company_id) REFERENCES companies (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE financing_requests ADD CONSTRAINT FK_BDA8926458C21047 FOREIGN KEY (factor_account_id) REFERENCES banking_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE financing_requests ADD CONSTRAINT FK_BDA89264FA9A9253 FOREIGN KEY (supplier_account_id) REFERENCES banking_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE financing_requests ADD CONSTRAINT FK_BDA89264B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE financing_requests ADD CONSTRAINT FK_BDA892642D234F6A FOREIGN KEY (approved_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE financing_requests ADD CONSTRAINT FK_BDA892642FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transactions (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE messenger_messages');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE messenger_messages_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_75ea56e016ba31db ON messenger_messages (delivered_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0e3bd61ce ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX idx_75ea56e0fb7336f0 ON messenger_messages (queue_name)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE financing_requests DROP CONSTRAINT FK_BDA89264F66AD73F');
        $this->addSql('ALTER TABLE financing_requests DROP CONSTRAINT FK_BDA89264EDC45283');
        $this->addSql('ALTER TABLE financing_requests DROP CONSTRAINT FK_BDA8926458C21047');
        $this->addSql('ALTER TABLE financing_requests DROP CONSTRAINT FK_BDA89264FA9A9253');
        $this->addSql('ALTER TABLE financing_requests DROP CONSTRAINT FK_BDA89264B03A8386');
        $this->addSql('ALTER TABLE financing_requests DROP CONSTRAINT FK_BDA892642D234F6A');
        $this->addSql('ALTER TABLE financing_requests DROP CONSTRAINT FK_BDA892642FC0CB0F');
        $this->addSql('DROP TABLE financing_requests');
    }
}
