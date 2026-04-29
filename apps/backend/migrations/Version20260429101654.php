<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260429101654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_company_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_representative VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_address TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_phone VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_tax_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD legal_notice_vat_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE global_settings ADD privacy_policy_pdf_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_company_name');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_representative');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_address');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_email');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_phone');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_tax_id');
        $this->addSql('ALTER TABLE global_settings DROP legal_notice_vat_id');
        $this->addSql('ALTER TABLE global_settings DROP privacy_policy_pdf_path');
    }
}
