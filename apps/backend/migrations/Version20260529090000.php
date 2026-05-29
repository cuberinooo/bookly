<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auto-cancel settings to global_settings table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE global_settings ADD auto_cancel_enabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE global_settings ADD auto_cancel_min_participants INT DEFAULT 3 NOT NULL');
        $this->addSql('ALTER TABLE global_settings ADD auto_cancel_hours_before INT DEFAULT 4 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE global_settings DROP auto_cancel_enabled');
        $this->addSql('ALTER TABLE global_settings DROP auto_cancel_min_participants');
        $this->addSql('ALTER TABLE global_settings DROP auto_cancel_hours_before');
    }
}
