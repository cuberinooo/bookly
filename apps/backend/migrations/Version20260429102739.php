<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260429102739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_street VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_house_number VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_zip_code VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_city VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_address');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_address TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_street');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_house_number');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_zip_code');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_city');
    }
}
