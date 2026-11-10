<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110111201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial : utilisateurs, chevaux, boîtiers, mesures, alertes';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alert (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(255) NOT NULL, severity VARCHAR(255) NOT NULL, message VARCHAR(255) NOT NULL, triggered_at DATETIME NOT NULL, created_at DATETIME NOT NULL, acknowledged_at DATETIME DEFAULT NULL, horse_id INTEGER NOT NULL, CONSTRAINT FK_17FD46C176B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX alert_horse_open_idx ON alert (horse_id, acknowledged_at)');
        $this->addSql('CREATE INDEX IDX_17FD46C176B275AD ON alert (horse_id)');
        $this->addSql('CREATE TABLE device (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, serial VARCHAR(32) NOT NULL, token_hash VARCHAR(64) NOT NULL, battery_level INTEGER DEFAULT NULL, last_seen_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, horse_id INTEGER DEFAULT NULL, CONSTRAINT FK_92FB68E76B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68ED374C9DC ON device (serial)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68EB3BC57DA ON device (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_92FB68E76B275AD ON device (horse_id)');
        $this->addSql('CREATE TABLE horse (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, breed VARCHAR(80) DEFAULT NULL, birth_year INTEGER DEFAULT NULL, clip VARCHAR(255) NOT NULL, cold_sensitive BOOLEAN NOT NULL, has_shelter BOOLEAN NOT NULL, current_blanket VARCHAR(255) NOT NULL, paddock CLOB NOT NULL, created_at DATETIME NOT NULL, owner_id INTEGER NOT NULL, CONSTRAINT FK_629A2F187E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_629A2F187E3C61F9 ON horse (owner_id)');
        $this->addSql('CREATE TABLE reading (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, recorded_at DATETIME NOT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, temperature DOUBLE PRECISION DEFAULT NULL, humidity DOUBLE PRECISION DEFAULT NULL, outside_temperature DOUBLE PRECISION DEFAULT NULL, activity DOUBLE PRECISION DEFAULT NULL, gait VARCHAR(255) DEFAULT NULL, rolls INTEGER NOT NULL, heart_rate INTEGER DEFAULT NULL, battery INTEGER DEFAULT NULL, horse_id INTEGER NOT NULL, CONSTRAINT FK_C11AFC4176B275AD FOREIGN KEY (horse_id) REFERENCES horse (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX reading_horse_time_idx ON reading (horse_id, recorded_at)');
        $this->addSql('CREATE INDEX IDX_C11AFC4176B275AD ON reading (horse_id)');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(80) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE alert');
        $this->addSql('DROP TABLE device');
        $this->addSql('DROP TABLE horse');
        $this->addSql('DROP TABLE reading');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
