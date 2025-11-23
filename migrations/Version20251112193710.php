<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour convertir les colonnes unit_price et total_ht de INT vers DECIMAL
 * 
 * Cette migration modifie le type de données des colonnes unit_price et total_ht
 * dans les tables quote_lines et invoice_lines de INTEGER vers DECIMAL(10,2)
 * pour permettre le stockage des montants en euros avec 2 décimales.
 */
final class Version20251112193710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convertir unit_price et total_ht de INT vers DECIMAL(10,2) dans quote_lines et invoice_lines';
    }

    public function up(Schema $schema): void
    {
        // Convertir les colonnes dans quote_lines
        // D'abord, convertir les données existantes (centimes -> euros) si nécessaire
        // Puis modifier le type de colonne
        $this->addSql("
            ALTER TABLE quote_lines 
            ALTER COLUMN unit_price TYPE NUMERIC(10, 2) USING unit_price::numeric / 100.0
        ");
        
        $this->addSql("
            ALTER TABLE quote_lines 
            ALTER COLUMN total_ht TYPE NUMERIC(10, 2) USING total_ht::numeric / 100.0
        ");

        // Convertir les colonnes dans invoice_lines
        $this->addSql("
            ALTER TABLE invoice_lines 
            ALTER COLUMN unit_price TYPE NUMERIC(10, 2) USING unit_price::numeric / 100.0
        ");
        
        $this->addSql("
            ALTER TABLE invoice_lines 
            ALTER COLUMN total_ht TYPE NUMERIC(10, 2) USING total_ht::numeric / 100.0
        ");
    }

    public function down(Schema $schema): void
    {
        // Reconvertir les colonnes de DECIMAL vers INT (multiplier par 100 pour convertir euros -> centimes)
        // ATTENTION : Cette opération peut entraîner une perte de précision
        
        $this->addSql("
            ALTER TABLE quote_lines 
            ALTER COLUMN unit_price TYPE INTEGER USING ROUND(unit_price * 100)::integer
        ");
        
        $this->addSql("
            ALTER TABLE quote_lines 
            ALTER COLUMN total_ht TYPE INTEGER USING ROUND(total_ht * 100)::integer
        ");

        $this->addSql("
            ALTER TABLE invoice_lines 
            ALTER COLUMN unit_price TYPE INTEGER USING ROUND(unit_price * 100)::integer
        ");
        
        $this->addSql("
            ALTER TABLE invoice_lines 
            ALTER COLUMN total_ht TYPE INTEGER USING ROUND(total_ht * 100)::integer
        ");
    }
}

