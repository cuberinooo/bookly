<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove unused last_generated_date from course_series.
 */
final class Version20260525110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unused last_generated_date from course_series.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_series DROP COLUMN last_generated_date');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course_series ADD last_generated_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }
}
