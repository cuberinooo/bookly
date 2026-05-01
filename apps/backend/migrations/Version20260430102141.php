<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260430102141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course ADD reminder_sent BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD course_start_notification_hours INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE "user" ADD course_start_notification_minutes INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course DROP reminder_sent');
        $this->addSql('ALTER TABLE "user" DROP course_start_notification_hours');
        $this->addSql('ALTER TABLE "user" DROP course_start_notification_minutes');
    }
}
