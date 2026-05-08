<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251227172159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблицы password_reset_tokens для сброса пароля';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE password_reset_tokens (
                id UUID NOT NULL PRIMARY KEY,
                user_id UUID NOT NULL,
                token VARCHAR(64) NOT NULL UNIQUE,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT fk_password_reset_tokens_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');

        $this->addSql('CREATE INDEX idx_token ON password_reset_tokens (token)');
        $this->addSql('CREATE INDEX idx_user_id ON password_reset_tokens (user_id)');

        $this->addSql('COMMENT ON COLUMN password_reset_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.used_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
    }
}
