<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251227214607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_keys (id UUID NOT NULL, user_id UUID NOT NULL, name VARCHAR(255) NOT NULL, key_hash VARCHAR(64) NOT NULL, key_prefix VARCHAR(16) NOT NULL, scopes JSON NOT NULL, ip_whitelist JSON DEFAULT NULL, rate_limit INT NOT NULL, is_active BOOLEAN NOT NULL, last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9579321F57BFB971 ON api_keys (key_hash)');
        $this->addSql('CREATE INDEX idx_api_key_hash ON api_keys (key_hash)');
        $this->addSql('CREATE INDEX idx_api_key_user ON api_keys (user_id)');
        $this->addSql('CREATE INDEX idx_api_key_active ON api_keys (is_active)');
        $this->addSql('COMMENT ON COLUMN api_keys.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN api_keys.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN api_keys.last_used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN api_keys.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN api_keys.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN api_keys.revoked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE api_keys ADD CONSTRAINT FK_9579321FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE users ALTER security_version DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE api_keys DROP CONSTRAINT FK_9579321FA76ED395');
        $this->addSql('DROP TABLE api_keys');
        $this->addSql('ALTER TABLE users ALTER security_version SET DEFAULT 1');
    }
}
