<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour convertir les montants de centimes vers euros (DECIMAL)
 * 
 * Cette migration convertit toutes les données existantes qui sont stockées en centimes
 * vers des valeurs en euros pour uniformiser le stockage avec le nouveau format DECIMAL.
 */
final class Version20251112190515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convertir les montants de centimes vers euros dans quotes, quote_lines, invoices et invoice_lines';
    }

    public function up(Schema $schema): void
    {
        // Convertir les montants dans la table quotes
        // On divise par 100 seulement si la valeur est >= 100 (probablement en centimes)
        // pour éviter de convertir deux fois les données déjà en euros
        // Les valeurs < 100 sont probablement déjà en euros (ex: 20.00€)
        $this->addSql("
            UPDATE quotes 
            SET montant_ht = ROUND(montant_ht / 100, 2)
            WHERE montant_ht >= 100
        ");

        $this->addSql("
            UPDATE quotes 
            SET montant_ttc = ROUND(montant_ttc / 100, 2)
            WHERE montant_ttc >= 100
        ");

        // Convertir les montants dans la table quote_lines
        $this->addSql("
            UPDATE quote_lines 
            SET unit_price = ROUND(unit_price / 100, 2)
            WHERE unit_price >= 100
        ");

        $this->addSql("
            UPDATE quote_lines 
            SET total_ht = ROUND(total_ht / 100, 2)
            WHERE total_ht >= 100
        ");

        // Convertir les montants dans la table invoices
        // IMPORTANT: Les montants < 100 sont probablement déjà en euros
        // Les montants >= 100 sont probablement en centimes et doivent être divisés par 100
        $this->addSql("
            UPDATE invoices 
            SET montant_ht = ROUND(montant_ht / 100, 2)
            WHERE montant_ht >= 100
        ");

        $this->addSql("
            UPDATE invoices 
            SET montant_ttc = ROUND(montant_ttc / 100, 2)
            WHERE montant_ttc >= 100
        ");

        $this->addSql("
            UPDATE invoices 
            SET montant_tva = ROUND(montant_tva / 100, 2)
            WHERE montant_tva >= 100
        ");

        $this->addSql("
            UPDATE invoices 
            SET montant_acompte = ROUND(montant_acompte / 100, 2)
            WHERE montant_acompte >= 100
        ");

        // Convertir les montants dans la table invoice_lines
        $this->addSql("
            UPDATE invoice_lines 
            SET unit_price = ROUND(unit_price / 100, 2)
            WHERE unit_price >= 100
        ");

        $this->addSql("
            UPDATE invoice_lines 
            SET total_ht = ROUND(total_ht / 100, 2)
            WHERE total_ht >= 100
        ");
    }

    public function down(Schema $schema): void
    {
        // Convertir les montants de euros vers centimes (multiplier par 100)
        // ATTENTION : Cette opération peut entraîner une perte de précision

        // Convertir les montants dans la table quotes
        $this->addSql("
            UPDATE quotes 
            SET montant_ht = ROUND(montant_ht * 100, 0)
            WHERE montant_ht < 1000
        ");

        $this->addSql("
            UPDATE quotes 
            SET montant_ttc = ROUND(montant_ttc * 100, 0)
            WHERE montant_ttc < 1000
        ");

        // Convertir les montants dans la table quote_lines
        $this->addSql("
            UPDATE quote_lines 
            SET unit_price = ROUND(unit_price * 100, 0)
            WHERE unit_price < 1000
        ");

        $this->addSql("
            UPDATE quote_lines 
            SET total_ht = ROUND(total_ht * 100, 0)
            WHERE total_ht < 1000
        ");

        // Convertir les montants dans la table invoices
        $this->addSql("
            UPDATE invoices 
            SET montant_ht = ROUND(montant_ht * 100, 0)
            WHERE montant_ht < 1000
        ");

        $this->addSql("
            UPDATE invoices 
            SET montant_ttc = ROUND(montant_ttc * 100, 0)
            WHERE montant_ttc < 1000
        ");

        $this->addSql("
            UPDATE invoices 
            SET montant_tva = ROUND(montant_tva * 100, 0)
            WHERE montant_tva < 1000
        ");

        $this->addSql("
            UPDATE invoices 
            SET montant_acompte = ROUND(montant_acompte * 100, 0)
            WHERE montant_acompte < 1000
        ");

        // Convertir les montants dans la table invoice_lines
        $this->addSql("
            UPDATE invoice_lines 
            SET unit_price = ROUND(unit_price * 100, 0)
            WHERE unit_price < 1000
        ");

        $this->addSql("
            UPDATE invoice_lines 
            SET total_ht = ROUND(total_ht * 100, 0)
            WHERE total_ht < 1000
        ");
    }
}
