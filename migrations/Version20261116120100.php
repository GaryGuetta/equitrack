<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mesures importées de Supabase : identifiant de la ligne d\'origine';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->platform instanceof SQLitePlatform, 'Migration SQLite : sur PostgreSQL, la colonne est créée par Version20261116120000.');

        $this->addSql('ALTER TABLE reading ADD COLUMN source_id BIGINT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C11AFC41953C1C61 ON reading (source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(!$this->platform instanceof SQLitePlatform, 'Migration SQLite.');

        $this->addSql('DROP INDEX UNIQ_C11AFC41953C1C61');
        $this->addSql('ALTER TABLE reading DROP COLUMN source_id');
    }
}
