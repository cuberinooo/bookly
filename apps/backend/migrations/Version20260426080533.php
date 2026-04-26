<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260426080533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT fk_e00cedde7597d3fe');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT fk_e00cedde591cc992');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE7597D3FE FOREIGN KEY (member_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE7597D3FE');
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE591CC992');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT fk_e00cedde7597d3fe FOREIGN KEY (member_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT fk_e00cedde591cc992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
