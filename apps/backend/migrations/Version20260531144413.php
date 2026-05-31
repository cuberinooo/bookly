<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add auto_cancelled column to course table.
 */
final class Version20260531144413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auto_cancelled column to course table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course ADD auto_cancelled BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP auto_cancelled');
    }
}
