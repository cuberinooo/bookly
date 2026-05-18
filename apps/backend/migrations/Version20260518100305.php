<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260518100305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course ADD status VARCHAR(255) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE course ADD postponed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB91734B47F FOREIGN KEY (postponed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_169E6FB91734B47F ON course (postponed_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course DROP CONSTRAINT FK_169E6FB91734B47F');
        $this->addSql('DROP INDEX IDX_169E6FB91734B47F');
        $this->addSql('ALTER TABLE course DROP status');
        $this->addSql('ALTER TABLE course DROP postponed_by_id');
    }
}
