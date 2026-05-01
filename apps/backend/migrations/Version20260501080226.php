<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501080226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_71e5a7c31d4e64e8');
        $this->addSql('ALTER TABLE admin_settings DROP company_name');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_settings ADD company_name VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_71e5a7c31d4e64e8 ON admin_settings (company_name)');
    }
}
