<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116094020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Plus de sonde extérieure : suppression de la température extérieure mesurée';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(!$this->platform instanceof SQLitePlatform, 'Migration SQLite : sur PostgreSQL, le schéma est créé par Version20261116120000.');

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__reading AS
            SELECT
              id,
              recorded_at,
              latitude,
              longitude,
              temperature,
              humidity,
              activity,
              gait,
              rolls,
              heart_rate,
              battery,
              horse_id
            FROM
              reading
        SQL);
        $this->addSql('DROP TABLE reading');
        $this->addSql(<<<'SQL'
            CREATE TABLE reading (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              recorded_at DATETIME NOT NULL,
              latitude DOUBLE PRECISION DEFAULT NULL,
              longitude DOUBLE PRECISION DEFAULT NULL,
              temperature DOUBLE PRECISION DEFAULT NULL,
              humidity DOUBLE PRECISION DEFAULT NULL,
              activity DOUBLE PRECISION DEFAULT NULL,
              gait VARCHAR(255) DEFAULT NULL,
              rolls INTEGER NOT NULL,
              heart_rate INTEGER DEFAULT NULL,
              battery INTEGER DEFAULT NULL,
              horse_id INTEGER NOT NULL,
              CONSTRAINT FK_C11AFC4176B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) ON
              UPDATE
                NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO reading (
              id, recorded_at, latitude, longitude,
              temperature, humidity, activity,
              gait, rolls, heart_rate, battery,
              horse_id
            )
            SELECT
              id,
              recorded_at,
              latitude,
              longitude,
              temperature,
              humidity,
              activity,
              gait,
              rolls,
              heart_rate,
              battery,
              horse_id
            FROM
              __temp__reading
        SQL);
        $this->addSql('DROP TABLE __temp__reading');
        $this->addSql('CREATE INDEX IDX_C11AFC4176B275AD ON reading (horse_id)');
        $this->addSql('CREATE INDEX reading_horse_time_idx ON reading (horse_id, recorded_at)');
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(!$this->platform instanceof SQLitePlatform, 'Migration SQLite.');

        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reading ADD COLUMN outside_temperature DOUBLE PRECISION DEFAULT NULL');
    }
}
