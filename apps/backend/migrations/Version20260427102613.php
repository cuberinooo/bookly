<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427102613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course DROP CONSTRAINT fk_169e6fb9fb08edf6');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9FB08EDF6 FOREIGN KEY (trainer_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course DROP CONSTRAINT FK_169E6FB9FB08EDF6');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT fk_169e6fb9fb08edf6 FOREIGN KEY (trainer_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
