<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add membership_welcome_mail_sent to user table.
 */
final class Version20260604130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add membership_welcome_mail_sent to user table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD membership_welcome_mail_sent BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP membership_welcome_mail_sent');
    }
}
