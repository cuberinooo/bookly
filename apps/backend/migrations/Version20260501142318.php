<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501142318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD company_id INT DEFAULT 1');
        $this->addSql('UPDATE booking SET company_id = 1');
        $this->addSql('ALTER TABLE booking ALTER COLUMN company_id SET NOT NULL');
        $this->addSql('ALTER TABLE booking ALTER COLUMN company_id DROP DEFAULT');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE979B1AD6 FOREIGN KEY (company_id) REFERENCES company (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E00CEDDE979B1AD6 ON booking (company_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE979B1AD6');
        $this->addSql('DROP INDEX IDX_E00CEDDE979B1AD6');
        $this->addSql('ALTER TABLE booking DROP company_id');
    }
}
