<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251022195445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // user and messenger_messages already created in Version20251019104712
        $this->addSql('CREATE TABLE course (id SERIAL NOT NULL, teacher_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, max_capacity INT NOT NULL, current_enrollment INT NOT NULL, schedule VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_169E6FB941807E1D ON course (teacher_id)');
        $this->addSql('CREATE TABLE enrollment (id SERIAL NOT NULL, student_id INT NOT NULL, course_id INT NOT NULL, enrolled_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DBDCD7E1CB944F1A ON enrollment (student_id)');
        $this->addSql('CREATE INDEX IDX_DBDCD7E1591CC992 ON enrollment (course_id)');
        $this->addSql('CREATE TABLE interest_profile (id SERIAL NOT NULL, interests JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB941807E1D FOREIGN KEY (teacher_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E1CB944F1A FOREIGN KEY (student_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E1591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE course DROP CONSTRAINT FK_169E6FB941807E1D');
        $this->addSql('ALTER TABLE enrollment DROP CONSTRAINT FK_DBDCD7E1CB944F1A');
        $this->addSql('ALTER TABLE enrollment DROP CONSTRAINT FK_DBDCD7E1591CC992');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE enrollment');
        $this->addSql('DROP TABLE interest_profile');
    }
}
