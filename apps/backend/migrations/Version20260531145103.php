<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Update existing 'postponed' course statuses to 'cancelled'.
 */
final class Version20260531145103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Update existing 'postponed' course statuses to 'cancelled'";
    }

    public function up(Schema $schema): void
    {
        // This is a data migration to ensure consistency after renaming POSTPONED to CANCELLED in the Enum
        $this->addSql("UPDATE course SET status = 'cancelled' WHERE status = 'postponed'");
    }

    public function down(Schema $schema): void
    {
        // Revert back if needed
        $this->addSql("UPDATE course SET status = 'postponed' WHERE status = 'cancelled'");
    }
}
