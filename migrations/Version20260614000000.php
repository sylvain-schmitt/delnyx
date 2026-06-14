<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ category (B2B/B2C) sur les clients pour la facturation électronique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE clients ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'particulier'");

        // Backfill : clients ayant un SIRET → professionnel (B2B)
        $this->addSql("UPDATE clients SET category = 'professionnel' WHERE siret IS NOT NULL AND siret != ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients DROP COLUMN category');
    }
}
