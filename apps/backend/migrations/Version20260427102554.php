<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260427102554 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT fk_bf5476caa76ed395');
        $this->addSql('ALTER TABLE notification ADD course_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_BF5476CA591CC992 ON notification (course_id)');
        $this->addSql('ALTER TABLE "user" ALTER is_active SET DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE notification DROP CONSTRAINT FK_BF5476CA591CC992');
        $this->addSql('DROP INDEX IDX_BF5476CA591CC992');
        $this->addSql('ALTER TABLE notification DROP course_id');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT fk_bf5476caa76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ALTER is_active SET DEFAULT true');
    }
}
