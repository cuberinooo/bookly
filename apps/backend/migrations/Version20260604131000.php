<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add payment_enabled to stripe_config table.
 */
final class Version20260604131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment_enabled to stripe_config table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stripe_config ADD payment_enabled BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stripe_config DROP payment_enabled');
    }
}
