<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photo du cheval';
    }

    public function up(Schema $schema): void
    {
        // Même syntaxe pour SQLite et PostgreSQL.
        $this->addSql('ALTER TABLE horse ADD photo VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE horse DROP COLUMN photo');
    }
}
