<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907133309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table clients avec toutes les colonnes nécessaires';
    }

    public function up(Schema $schema): void
    {
        // Création de la table clients
        $this->addSql('CREATE TABLE clients (
            id SERIAL PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            telephone VARCHAR(20) DEFAULT NULL,
            adresse VARCHAR(255) DEFAULT NULL,
            code_postal VARCHAR(10) DEFAULT NULL,
            ville VARCHAR(100) DEFAULT NULL,
            pays VARCHAR(100) NOT NULL DEFAULT \'France\',
            siret VARCHAR(14) DEFAULT NULL,
            tva_intracommunautaire VARCHAR(20) DEFAULT NULL,
            statut VARCHAR(20) NOT NULL DEFAULT \'prospect\',
            date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            notes TEXT DEFAULT NULL
        )');

        // Création de la table devis (référence temporaire)
        $this->addSql('CREATE TABLE devis (
            id SERIAL PRIMARY KEY,
            client_id INTEGER NOT NULL,
            CONSTRAINT FK_8B27C52B19EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
        )');

        // Création de la table factures (référence temporaire)
        $this->addSql('CREATE TABLE factures (
            id SERIAL PRIMARY KEY,
            client_id INTEGER NOT NULL,
            CONSTRAINT FK_647590B319EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
        )');

        // Création des index
        $this->addSql('CREATE INDEX IDX_8B27C52B19EB6921 ON devis (client_id)');
        $this->addSql('CREATE INDEX IDX_647590B319EB6921 ON factures (client_id)');
        $this->addSql('CREATE INDEX IDX_clients_email ON clients (email)');
        $this->addSql('CREATE INDEX IDX_clients_statut ON clients (statut)');
        $this->addSql('CREATE INDEX IDX_clients_date_creation ON clients (date_creation)');
    }

    public function down(Schema $schema): void
    {
        // Suppression des tables dans l'ordre inverse
        $this->addSql('DROP TABLE factures');
        $this->addSql('DROP TABLE devis');
        $this->addSql('DROP TABLE clients');
    }
}
