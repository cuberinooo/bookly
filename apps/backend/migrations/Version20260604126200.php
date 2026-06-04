<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260604126200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_settings RENAME COLUMN join_us_mail_markdown TO membership_welcome_mail_markdown');
        $this->addSql('ALTER TABLE admin_settings RENAME COLUMN join_us_mail_attachments TO membership_welcome_mail_attachments');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_settings RENAME COLUMN membership_welcome_mail_markdown TO join_us_mail_markdown');
        $this->addSql('ALTER TABLE admin_settings RENAME COLUMN membership_welcome_mail_attachments TO join_us_mail_attachments');
    }
}
