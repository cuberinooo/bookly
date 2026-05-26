<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260526080015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise ALTER unit DROP DEFAULT');
        $this->addSql('ALTER TABLE training_category DROP CONSTRAINT fk_e1290a56fb08edf6');
        $this->addSql('DROP INDEX idx_e1290a56fb08edf6');
        $this->addSql('ALTER TABLE training_category DROP trainer_id');
        $this->addSql('ALTER TABLE training_cycle DROP CONSTRAINT fk_5f5c5bfefb08edf6');
        $this->addSql('DROP INDEX idx_5f5c5bfefb08edf6');
        $this->addSql('ALTER TABLE training_cycle DROP trainer_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise ALTER unit SET DEFAULT \'kg\'');
        $this->addSql('ALTER TABLE training_category ADD trainer_id INT NOT NULL');
        $this->addSql('ALTER TABLE training_category ADD CONSTRAINT fk_e1290a56fb08edf6 FOREIGN KEY (trainer_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_e1290a56fb08edf6 ON training_category (trainer_id)');
        $this->addSql('ALTER TABLE training_cycle ADD trainer_id INT NOT NULL');
        $this->addSql('ALTER TABLE training_cycle ADD CONSTRAINT fk_5f5c5bfefb08edf6 FOREIGN KEY (trainer_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_5f5c5bfefb08edf6 ON training_cycle (trainer_id)');
    }
}
