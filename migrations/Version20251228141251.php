<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228141251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT fk_eaa81a4c2d234f6a');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT fk_eaa81a4cb03a8386');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT fk_eaa81a4cb0cf99bd');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT fk_eaa81a4cbc58bdc7');
        $this->addSql('DROP INDEX idx_eaa81a4c2d234f6a');
        $this->addSql('DROP INDEX idx_eaa81a4cb03a8386');
        $this->addSql('DROP INDEX idx_transactions_created');
        $this->addSql('DROP INDEX idx_transactions_from');
        $this->addSql('DROP INDEX idx_transactions_to');
        $this->addSql('DROP INDEX idx_transactions_type');
        $this->addSql('DROP INDEX uniq_eaa81a4c8bf1ae50');
        $this->addSql('ALTER TABLE transactions ADD debit_account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE transactions ADD credit_account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE transactions ADD initiated_by_id UUID NOT NULL');
        $this->addSql('ALTER TABLE transactions ADD amount_minor_units BIGINT NOT NULL');
        $this->addSql('ALTER TABLE transactions ADD idempotency_key UUID NOT NULL');
        $this->addSql('ALTER TABLE transactions ADD related_entity_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD related_entity_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions DROP from_account_id');
        $this->addSql('ALTER TABLE transactions DROP to_account_id');
        $this->addSql('ALTER TABLE transactions DROP created_by_id');
        $this->addSql('ALTER TABLE transactions DROP approved_by_id');
        $this->addSql('ALTER TABLE transactions DROP amount');
        $this->addSql('ALTER TABLE transactions DROP purpose');
        $this->addSql('ALTER TABLE transactions DROP reference_number');
        $this->addSql('ALTER TABLE transactions DROP approved_at');
        $this->addSql('ALTER TABLE transactions DROP completed_at');
        $this->addSql('ALTER TABLE transactions DROP failure_reason');
        $this->addSql('ALTER TABLE transactions ALTER type TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN updated_at TO executed_at');
        $this->addSql('COMMENT ON COLUMN transactions.debit_account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.credit_account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.initiated_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.idempotency_key IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.related_entity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C204C4EAA FOREIGN KEY (debit_account_id) REFERENCES banking_accounts (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4C6813E404 FOREIGN KEY (credit_account_id) REFERENCES banking_accounts (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CC4EF1FC7 FOREIGN KEY (initiated_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAA81A4C7FD1C147 ON transactions (idempotency_key)');
        $this->addSql('CREATE INDEX IDX_EAA81A4CC4EF1FC7 ON transactions (initiated_by_id)');
        $this->addSql('CREATE INDEX idx_debit_account ON transactions (debit_account_id)');
        $this->addSql('CREATE INDEX idx_credit_account ON transactions (credit_account_id)');
        $this->addSql('CREATE INDEX idx_idempotency_key ON transactions (idempotency_key)');
        $this->addSql('CREATE INDEX idx_executed_at ON transactions (executed_at)');
        $this->addSql('ALTER INDEX idx_transactions_status RENAME TO idx_status');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4C204C4EAA');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4C6813E404');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4CC4EF1FC7');
        $this->addSql('DROP INDEX UNIQ_EAA81A4C7FD1C147');
        $this->addSql('DROP INDEX IDX_EAA81A4CC4EF1FC7');
        $this->addSql('DROP INDEX idx_debit_account');
        $this->addSql('DROP INDEX idx_credit_account');
        $this->addSql('DROP INDEX idx_idempotency_key');
        $this->addSql('DROP INDEX idx_executed_at');
        $this->addSql('ALTER TABLE transactions ADD to_account_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD created_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD approved_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD amount NUMERIC(15, 2) NOT NULL');
        $this->addSql('ALTER TABLE transactions ADD purpose TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD reference_number VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE transactions ADD approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions ADD failure_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE transactions DROP debit_account_id');
        $this->addSql('ALTER TABLE transactions DROP credit_account_id');
        $this->addSql('ALTER TABLE transactions DROP initiated_by_id');
        $this->addSql('ALTER TABLE transactions DROP amount_minor_units');
        $this->addSql('ALTER TABLE transactions DROP idempotency_key');
        $this->addSql('ALTER TABLE transactions DROP related_entity_type');
        $this->addSql('ALTER TABLE transactions ALTER type TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN related_entity_id TO from_account_id');
        $this->addSql('ALTER TABLE transactions RENAME COLUMN executed_at TO updated_at');
        $this->addSql('COMMENT ON COLUMN transactions.to_account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.created_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.approved_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN transactions.approved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN transactions.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT fk_eaa81a4c2d234f6a FOREIGN KEY (approved_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT fk_eaa81a4cb03a8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT fk_eaa81a4cb0cf99bd FOREIGN KEY (from_account_id) REFERENCES bank_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT fk_eaa81a4cbc58bdc7 FOREIGN KEY (to_account_id) REFERENCES bank_accounts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_eaa81a4c2d234f6a ON transactions (approved_by_id)');
        $this->addSql('CREATE INDEX idx_eaa81a4cb03a8386 ON transactions (created_by_id)');
        $this->addSql('CREATE INDEX idx_transactions_created ON transactions (created_at)');
        $this->addSql('CREATE INDEX idx_transactions_from ON transactions (from_account_id)');
        $this->addSql('CREATE INDEX idx_transactions_to ON transactions (to_account_id)');
        $this->addSql('CREATE INDEX idx_transactions_type ON transactions (type)');
        $this->addSql('CREATE UNIQUE INDEX uniq_eaa81a4c8bf1ae50 ON transactions (reference_number)');
        $this->addSql('ALTER INDEX idx_status RENAME TO idx_transactions_status');
    }
}
