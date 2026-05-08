<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251227230952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_logs (id UUID NOT NULL, user_id UUID DEFAULT NULL, action VARCHAR(100) NOT NULL, entity_type VARCHAR(100) DEFAULT NULL, entity_id UUID DEFAULT NULL, payload JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, http_method VARCHAR(10) NOT NULL, request_uri VARCHAR(255) NOT NULL, status_code INT DEFAULT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_audit_logs_user_id ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX idx_audit_logs_action ON audit_logs (action)');
        $this->addSql('CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at)');
        $this->addSql('CREATE INDEX idx_audit_logs_entity ON audit_logs (entity_type, entity_id)');
        $this->addSql('COMMENT ON COLUMN audit_logs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_logs.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN audit_logs.entity_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE audit_logs DROP CONSTRAINT FK_D62F2858A76ED395');
        $this->addSql('DROP TABLE audit_logs');
    }
}
