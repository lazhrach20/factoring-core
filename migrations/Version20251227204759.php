<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251227204759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add security_version field to users table for token invalidation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD security_version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP security_version');
    }
}
