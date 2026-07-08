<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708184203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ai_signal table for student interaction tracking';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_signal (id SERIAL NOT NULL, student_id INT DEFAULT NULL, course_id INT DEFAULT NULL, school_id INT DEFAULT NULL, action VARCHAR(50) NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_72E697D1591CC992 ON ai_signal (course_id)');
        $this->addSql('CREATE INDEX IDX_72E697D1C32A47EE ON ai_signal (school_id)');
        $this->addSql('CREATE INDEX idx_ai_signal_student ON ai_signal (student_id)');
        $this->addSql('CREATE INDEX idx_ai_signal_created_at ON ai_signal (created_at)');
        $this->addSql('COMMENT ON COLUMN ai_signal.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE ai_signal ADD CONSTRAINT FK_72E697D1CB944F1A FOREIGN KEY (student_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_signal ADD CONSTRAINT FK_72E697D1591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE ai_signal ADD CONSTRAINT FK_72E697D1C32A47EE FOREIGN KEY (school_id) REFERENCES school (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE ai_signal DROP CONSTRAINT FK_72E697D1CB944F1A');
        $this->addSql('ALTER TABLE ai_signal DROP CONSTRAINT FK_72E697D1591CC992');
        $this->addSql('ALTER TABLE ai_signal DROP CONSTRAINT FK_72E697D1C32A47EE');
        $this->addSql('DROP TABLE ai_signal');
    }
}
