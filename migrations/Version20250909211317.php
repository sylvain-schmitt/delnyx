<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250909211317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration consolidée : Système de devis et tarifs complet';
    }

    public function up(Schema $schema): void
    {
        // La table clients existe déjà en production, on ne la recrée pas

        // Création de la table tarifs
        $this->addSql('CREATE TABLE tarifs (
            id SERIAL PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            categorie VARCHAR(50) NOT NULL,
            description TEXT DEFAULT NULL,
            prix DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            unite VARCHAR(20) DEFAULT \'forfait\',
            actif BOOLEAN DEFAULT true,
            ordre INTEGER DEFAULT 0,
            caracteristiques TEXT DEFAULT NULL,
            date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');

        // Création de la table devis
        $this->addSql('CREATE TABLE devis (
            id SERIAL PRIMARY KEY,
            numero VARCHAR(50) NOT NULL UNIQUE,
            client_id INTEGER NOT NULL,
            date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            date_validite TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            statut VARCHAR(20) DEFAULT \'brouillon\',
            montant_ht DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            taux_tva DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            montant_ttc DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            acompte_pourcentage DECIMAL(5,2) NOT NULL DEFAULT 30.00,
            conditions_paiement TEXT DEFAULT NULL,
            delai_livraison VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            date_acceptation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            signature_client TEXT DEFAULT NULL,
            adresse_livraison TEXT DEFAULT NULL,
            type_operations VARCHAR(50) DEFAULT NULL,
            paiement_tva_sur_debits BOOLEAN DEFAULT false,
            date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            FOREIGN KEY (client_id) REFERENCES clients(id)
        )');

        // Création de la table de liaison devis_tarifs
        $this->addSql('CREATE TABLE devis_tarifs (
            devis_id INTEGER NOT NULL,
            tarif_id INTEGER NOT NULL,
            PRIMARY KEY (devis_id, tarif_id),
            FOREIGN KEY (devis_id) REFERENCES devis(id) ON DELETE CASCADE,
            FOREIGN KEY (tarif_id) REFERENCES tarifs(id) ON DELETE CASCADE
        )');

        // Création de la table factures
        $this->addSql('CREATE TABLE factures (
            id SERIAL PRIMARY KEY,
            devis_id INTEGER NOT NULL UNIQUE,
            client_id INTEGER NOT NULL,
            FOREIGN KEY (devis_id) REFERENCES devis(id),
            FOREIGN KEY (client_id) REFERENCES clients(id)
        )');

        // Index pour les performances
        $this->addSql('CREATE INDEX IDX_tarifs_categorie ON tarifs(categorie)');
        $this->addSql('CREATE INDEX IDX_tarifs_actif ON tarifs(actif)');
        $this->addSql('CREATE INDEX IDX_devis_client ON devis(client_id)');
        $this->addSql('CREATE INDEX IDX_devis_statut ON devis(statut)');
        $this->addSql('CREATE INDEX IDX_devis_date_creation ON devis(date_creation)');
    }

    public function down(Schema $schema): void
    {
        // Suppression des tables dans l'ordre inverse
        $this->addSql('DROP TABLE factures');
        $this->addSql('DROP TABLE devis_tarifs');
        $this->addSql('DROP TABLE devis');
        $this->addSql('DROP TABLE tarifs');
        // La table clients n'est pas supprimée car elle existe déjà en production
    }
}
