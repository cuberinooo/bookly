<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename postponed_by_id to cancelled_by_id in course table.
 */
final class Version20260531143859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename postponed_by_id to cancelled_by_id in course table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP CONSTRAINT IF EXISTS fk_169e6fb91734b47f');
        $this->addSql('DROP INDEX IF EXISTS idx_169e6fb91734b47f');
        $this->addSql('ALTER TABLE course RENAME COLUMN postponed_by_id TO cancelled_by_id');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_169E6FB9187B2D12 ON course (cancelled_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP CONSTRAINT FK_169E6FB9187B2D12');
        $this->addSql('DROP INDEX IDX_169E6FB9187B2D12');
        $this->addSql('ALTER TABLE course RENAME COLUMN cancelled_by_id TO postponed_by_id');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT fk_169e6fb91734b47f FOREIGN KEY (postponed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_169e6fb91734b47f ON course (postponed_by_id)');
    }
}
