<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501073458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE global_settings ADD site_name VARCHAR(255) DEFAULT \'Phoenix Athletics\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3223F6EB65B9C3FF ON global_settings (site_name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_3223F6EB65B9C3FF');
        $this->addSql('ALTER TABLE global_settings DROP site_name');
    }
}
