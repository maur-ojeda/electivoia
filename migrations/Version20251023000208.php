<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251023000208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE interest_profile ADD student_id INT NOT NULL');
        $this->addSql('ALTER TABLE interest_profile ADD CONSTRAINT FK_8E2DDDC1CB944F1A FOREIGN KEY (student_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E2DDDC1CB944F1A ON interest_profile (student_id)');
        $this->addSql('ALTER TABLE "user" DROP average_grade');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "user" ADD average_grade DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE interest_profile DROP CONSTRAINT FK_8E2DDDC1CB944F1A');
        $this->addSql('DROP INDEX UNIQ_8E2DDDC1CB944F1A');
        $this->addSql('ALTER TABLE interest_profile DROP student_id');
    }
}
