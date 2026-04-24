<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove showWaitlistNames setting.
 */
final class Version20260424150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove showWaitlistNames from global_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE global_settings DROP COLUMN show_waitlist_names');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE global_settings ADD COLUMN show_waitlist_names BOOLEAN DEFAULT true NOT NULL');
    }
}
