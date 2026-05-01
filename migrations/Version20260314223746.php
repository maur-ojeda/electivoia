<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260314223746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sprint 5 — Multitenancy foundation: School entity, school_id FK on user/course, default school for existing data';
    }

    public function up(Schema $schema): void
    {
        // 1. Create school table
        $this->addSql('CREATE TABLE school (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(100) NOT NULL, rbd VARCHAR(20) DEFAULT NULL, active BOOLEAN DEFAULT true NOT NULL, plan VARCHAR(20) DEFAULT \'free\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F99EDABB989D9B62 ON school (slug)');
        $this->addSql('COMMENT ON COLUMN school.created_at IS \'(DC2Type:datetime_immutable)\'');

        // 2. Insert the default school for existing single-tenant data
        $this->addSql("INSERT INTO school (name, slug, active, plan, created_at) VALUES ('Colegio Demo', 'default', true, 'free', NOW())");

        // 3. Add nullable school_id FK to course
        $this->addSql('ALTER TABLE course ADD school_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9C32A47EE FOREIGN KEY (school_id) REFERENCES school (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_169E6FB9C32A47EE ON course (school_id)');

        // 4. Add nullable school_id FK to user
        $this->addSql('ALTER TABLE "user" ADD school_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649C32A47EE FOREIGN KEY (school_id) REFERENCES school (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8D93D649C32A47EE ON "user" (school_id)');

        // 5. Assign all existing records to the default school (id=1)
        $this->addSql('UPDATE course SET school_id = 1 WHERE school_id IS NULL');
        $this->addSql('UPDATE "user" SET school_id = 1 WHERE school_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE course DROP CONSTRAINT FK_169E6FB9C32A47EE');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649C32A47EE');
        $this->addSql('DROP TABLE school');
        $this->addSql('DROP INDEX IDX_169E6FB9C32A47EE');
        $this->addSql('ALTER TABLE course DROP school_id');
        $this->addSql('DROP INDEX IDX_8D93D649C32A47EE');
        $this->addSql('ALTER TABLE "user" DROP school_id');
    }
}
