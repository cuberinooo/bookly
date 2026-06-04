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
        return 'Rename join_us_mail_sent to membership_welcome_mail_sent.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" RENAME COLUMN join_us_mail_sent TO membership_welcome_mail_sent');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" RENAME COLUMN membership_welcome_mail_sent TO join_us_mail_sent');
    }
}
