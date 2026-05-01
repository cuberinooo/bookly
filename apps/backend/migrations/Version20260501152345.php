<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260501152345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT fk_e00cedde7597d3fe');
        $this->addSql('DROP INDEX uniq_booking');
        $this->addSql('DROP INDEX idx_e00cedde7597d3fe');
        $this->addSql('ALTER TABLE booking RENAME COLUMN member_id TO user_id');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E00CEDDEA76ED395 ON booking (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BOOKING ON booking (user_id, course_id)');
        $this->addSql('ALTER TABLE course DROP CONSTRAINT fk_169e6fb9fb08edf6');
        $this->addSql('DROP INDEX idx_169e6fb9fb08edf6');
        $this->addSql('ALTER TABLE course RENAME COLUMN trainer_id TO user_id');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_169E6FB9A76ED395 ON course (user_id)');
        $this->addSql('ALTER TABLE course_series DROP CONSTRAINT fk_9428c060fb08edf6');
        $this->addSql('DROP INDEX idx_9428c060fb08edf6');
        $this->addSql('ALTER TABLE course_series RENAME COLUMN trainer_id TO user_id');
        $this->addSql('ALTER TABLE course_series ADD CONSTRAINT FK_9428C060A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_9428C060A76ED395 ON course_series (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDEA76ED395');
        $this->addSql('DROP INDEX IDX_E00CEDDEA76ED395');
        $this->addSql('DROP INDEX UNIQ_BOOKING');
        $this->addSql('ALTER TABLE booking RENAME COLUMN user_id TO member_id');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT fk_e00cedde7597d3fe FOREIGN KEY (member_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_e00cedde7597d3fe ON booking (member_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_booking ON booking (member_id, course_id)');
        $this->addSql('ALTER TABLE course DROP CONSTRAINT FK_169E6FB9A76ED395');
        $this->addSql('DROP INDEX IDX_169E6FB9A76ED395');
        $this->addSql('ALTER TABLE course RENAME COLUMN user_id TO trainer_id');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT fk_169e6fb9fb08edf6 FOREIGN KEY (trainer_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_169e6fb9fb08edf6 ON course (trainer_id)');
        $this->addSql('ALTER TABLE course_series DROP CONSTRAINT FK_9428C060A76ED395');
        $this->addSql('DROP INDEX IDX_9428C060A76ED395');
        $this->addSql('ALTER TABLE course_series RENAME COLUMN user_id TO trainer_id');
        $this->addSql('ALTER TABLE course_series ADD CONSTRAINT fk_9428c060fb08edf6 FOREIGN KEY (trainer_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_9428c060fb08edf6 ON course_series (trainer_id)');
    }
}
