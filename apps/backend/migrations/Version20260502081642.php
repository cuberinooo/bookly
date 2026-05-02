<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260502081642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company ALTER admin_settings_id SET NOT NULL');
        $this->addSql('ALTER TABLE company ALTER global_settings_id SET NOT NULL');
        $this->addSql('ALTER TABLE global_settings ADD trial_booking_limit INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company ALTER admin_settings_id DROP NOT NULL');
        $this->addSql('ALTER TABLE company ALTER global_settings_id DROP NOT NULL');
        $this->addSql('ALTER TABLE global_settings DROP trial_booking_limit');
    }
}
