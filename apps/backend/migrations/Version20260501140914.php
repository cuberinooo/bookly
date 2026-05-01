<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501140914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course ADD company_id INT DEFAULT 1');
        $this->addSql('UPDATE course SET company_id = 1');
        $this->addSql('ALTER TABLE course ALTER COLUMN company_id SET NOT NULL');
        $this->addSql('ALTER TABLE course ALTER COLUMN company_id DROP DEFAULT');

        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_169E6FB9979B1AD6 ON course (company_id)');

        $this->addSql('ALTER TABLE course_series ADD company_id INT DEFAULT 1');
        $this->addSql('UPDATE course_series SET company_id = 1');
        $this->addSql('ALTER TABLE course_series ALTER COLUMN company_id SET NOT NULL');
        $this->addSql('ALTER TABLE course_series ALTER COLUMN company_id DROP DEFAULT');

        $this->addSql('ALTER TABLE course_series ADD CONSTRAINT FK_9428C060979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_9428C060979B1AD6 ON course_series (company_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course DROP CONSTRAINT FK_169E6FB9979B1AD6');
        $this->addSql('DROP INDEX IDX_169E6FB9979B1AD6');
        $this->addSql('ALTER TABLE course DROP company_id');
        $this->addSql('ALTER TABLE course_series DROP CONSTRAINT FK_9428C060979B1AD6');
        $this->addSql('DROP INDEX IDX_9428C060979B1AD6');
        $this->addSql('ALTER TABLE course_series DROP company_id');
    }
}
