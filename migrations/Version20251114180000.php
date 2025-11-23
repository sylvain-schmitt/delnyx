<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer la table amendment_lines et convertir les montants en DECIMAL
 */
final class Version20251114180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Créer la table amendment_lines et convertir les montants Amendment/CreditNote en DECIMAL';
    }

    public function up(Schema $schema): void
    {
        // Créer la table amendment_lines
        $this->addSql('CREATE TABLE amendment_lines (
            id SERIAL PRIMARY KEY,
            amendment_id INT NOT NULL,
            tariff_id INT,
            description VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
            total_ht NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
            tva_rate NUMERIC(5, 2),
            CONSTRAINT FK_amendment_lines_amendment FOREIGN KEY (amendment_id) REFERENCES amendments (id) ON DELETE CASCADE,
            CONSTRAINT FK_amendment_lines_tariff FOREIGN KEY (tariff_id) REFERENCES tariffs (id) ON DELETE SET NULL
        )');
        
        $this->addSql('CREATE INDEX IDX_amendment_lines_amendment ON amendment_lines (amendment_id)');
        $this->addSql('CREATE INDEX IDX_amendment_lines_tariff ON amendment_lines (tariff_id)');

        // Convertir les montants de amendments de INT (centimes) vers DECIMAL (euros)
        $this->addSql('ALTER TABLE amendments 
            ALTER COLUMN montant_ht TYPE NUMERIC(10, 2) USING CASE 
                WHEN montant_ht >= 100 THEN montant_ht::NUMERIC / 100.0 
                ELSE montant_ht::NUMERIC 
            END,
            ALTER COLUMN montant_tva TYPE NUMERIC(10, 2) USING CASE 
                WHEN montant_tva >= 100 THEN montant_tva::NUMERIC / 100.0 
                ELSE montant_tva::NUMERIC 
            END,
            ALTER COLUMN montant_ttc TYPE NUMERIC(10, 2) USING CASE 
                WHEN montant_ttc >= 100 THEN montant_ttc::NUMERIC / 100.0 
                ELSE montant_ttc::NUMERIC 
            END');

        // Mettre à jour le statut pour utiliser l'enum
        $this->addSql("ALTER TABLE amendments ALTER COLUMN statut TYPE VARCHAR(20)");
        
        // Ajouter les colonnes de signature et date_modification si elles n'existent pas
        $this->addSql('ALTER TABLE amendments 
            ADD COLUMN IF NOT EXISTS date_signature TIMESTAMP(0) WITHOUT TIME ZONE,
            ADD COLUMN IF NOT EXISTS signature_client TEXT,
            ADD COLUMN IF NOT EXISTS date_modification TIMESTAMP(0) WITHOUT TIME ZONE');

        // Convertir les montants de credit_notes de INT (centimes) vers DECIMAL (euros)
        $this->addSql('ALTER TABLE credit_notes 
            ALTER COLUMN montant_ht TYPE NUMERIC(10, 2) USING CASE 
                WHEN montant_ht >= 100 THEN montant_ht::NUMERIC / 100.0 
                ELSE montant_ht::NUMERIC 
            END,
            ALTER COLUMN montant_tva TYPE NUMERIC(10, 2) USING CASE 
                WHEN montant_tva >= 100 THEN montant_tva::NUMERIC / 100.0 
                ELSE montant_tva::NUMERIC 
            END,
            ALTER COLUMN montant_ttc TYPE NUMERIC(10, 2) USING CASE 
                WHEN montant_ttc >= 100 THEN montant_ttc::NUMERIC / 100.0 
                ELSE montant_ttc::NUMERIC 
            END');

        // Mettre à jour le statut pour utiliser l'enum
        $this->addSql("ALTER TABLE credit_notes ALTER COLUMN status TYPE VARCHAR(20)");
        
        // Ajouter la colonne date_modification si elle n'existe pas
        $this->addSql('ALTER TABLE credit_notes 
            ADD COLUMN IF NOT EXISTS date_modification TIMESTAMP(0) WITHOUT TIME ZONE');
        
        // Ajouter la colonne taux_tva si elle n'existe pas
        $this->addSql('ALTER TABLE credit_notes 
            ADD COLUMN IF NOT EXISTS taux_tva NUMERIC(5, 2)');

        // Convertir les montants de credit_note_lines de INT (centimes) vers DECIMAL (euros)
        $this->addSql('ALTER TABLE credit_note_lines 
            ALTER COLUMN unit_price TYPE NUMERIC(10, 2) USING CASE 
                WHEN unit_price >= 100 THEN unit_price::NUMERIC / 100.0 
                ELSE unit_price::NUMERIC 
            END,
            ALTER COLUMN total_ht TYPE NUMERIC(10, 2) USING CASE 
                WHEN total_ht >= 100 THEN total_ht::NUMERIC / 100.0 
                ELSE total_ht::NUMERIC 
            END');
    }

    public function down(Schema $schema): void
    {
        // Supprimer la table amendment_lines
        $this->addSql('DROP TABLE IF EXISTS amendment_lines');

        // Reconvertir les montants en INT (centimes) - ATTENTION: perte de précision
        $this->addSql('ALTER TABLE amendments 
            ALTER COLUMN montant_ht TYPE INT USING ROUND(montant_ht * 100)::INT,
            ALTER COLUMN montant_tva TYPE INT USING ROUND(montant_tva * 100)::INT,
            ALTER COLUMN montant_ttc TYPE INT USING ROUND(montant_ttc * 100)::INT');

        $this->addSql('ALTER TABLE credit_notes 
            ALTER COLUMN montant_ht TYPE INT USING ROUND(montant_ht * 100)::INT,
            ALTER COLUMN montant_tva TYPE INT USING ROUND(montant_tva * 100)::INT,
            ALTER COLUMN montant_ttc TYPE INT USING ROUND(montant_ttc * 100)::INT');

        $this->addSql('ALTER TABLE credit_note_lines 
            ALTER COLUMN unit_price TYPE INT USING ROUND(unit_price * 100)::INT,
            ALTER COLUMN total_ht TYPE INT USING ROUND(total_ht * 100)::INT');

        // Supprimer les colonnes ajoutées
        $this->addSql('ALTER TABLE amendments 
            DROP COLUMN IF EXISTS date_signature,
            DROP COLUMN IF EXISTS signature_client,
            DROP COLUMN IF EXISTS date_modification');

        $this->addSql('ALTER TABLE credit_notes 
            DROP COLUMN IF EXISTS date_modification,
            DROP COLUMN IF EXISTS taux_tva');
    }
}

