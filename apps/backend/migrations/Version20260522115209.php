<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260522115209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company ADD stripe_account_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company ADD stripe_onboarding_complete BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE company ADD stripe_price_setup_fee_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company ADD stripe_price_membership_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD membership_status VARCHAR(255) DEFAULT \'trial\' NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD stripe_customer_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company DROP stripe_account_id');
        $this->addSql('ALTER TABLE company DROP stripe_onboarding_complete');
        $this->addSql('ALTER TABLE company DROP stripe_price_setup_fee_id');
        $this->addSql('ALTER TABLE company DROP stripe_price_membership_id');
        $this->addSql('ALTER TABLE "user" DROP membership_status');
        $this->addSql('ALTER TABLE "user" DROP stripe_customer_id');
    }
}
