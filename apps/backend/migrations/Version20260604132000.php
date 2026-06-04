<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add monthly_fee_amount and setup_fee_amount to stripe_config table.
 */
final class Version20260604132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add monthly_fee_amount and setup_fee_amount to stripe_config table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stripe_config ADD monthly_fee_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE stripe_config ADD setup_fee_amount INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stripe_config DROP monthly_fee_amount');
        $this->addSql('ALTER TABLE stripe_config DROP setup_fee_amount');
    }
}
