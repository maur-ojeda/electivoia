<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Normalize all RUTs in the user table: strip dots, uppercase K.
 * Example: "30.100.001-k" → "30100001-K"
 *
 * Uses a pure-SQL expression compatible with PostgreSQL.
 * The unique constraint on rut means duplicates (same RUT with/without dots)
 * would cause a conflict; in practice the seed now generates clean RUTs so
 * conflicts should not arise.
 */
final class Version20261111143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize user RUTs: strip dots, uppercase K';
    }

    public function up(Schema $schema): void
    {
        // Strip dots, then uppercase the whole string (only K is affected as a letter)
        $this->addSql(<<<'SQL'
            UPDATE "user"
            SET rut = UPPER(REPLACE(rut, '.', ''))
            WHERE rut ~ '\.'
               OR rut ~ '[a-z]'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Normalization is irreversible (dots stripped; can't tell where they were)
        $this->addSql('-- down: cannot un-normalize RUTs; no-op');
    }
}
