<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260520083059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cycle_assignment DROP CONSTRAINT fk_6011688212469de2');
        $this->addSql('ALTER TABLE cycle_assignment ADD CONSTRAINT FK_6011688212469DE2 FOREIGN KEY (category_id) REFERENCES training_category (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cycle_assignment DROP CONSTRAINT FK_6011688212469DE2');
        $this->addSql('ALTER TABLE cycle_assignment ADD CONSTRAINT fk_6011688212469de2 FOREIGN KEY (category_id) REFERENCES training_category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
