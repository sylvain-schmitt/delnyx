<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour convertir les montants de centimes vers euros dans la table invoices
 * 
 * Cette migration corrige les montants dans invoices qui sont stockés en centimes
 * (ex: 20.00 au lieu de 2000.00) en les multipliant par 100.
 * 
 * ATTENTION: Cette migration suppose que les montants < 100 sont en centimes
 * et doivent être multipliés par 100 pour obtenir les euros.
 */
final class Version20251112192603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convertir les montants de centimes vers euros dans invoices (multiplier par 100 pour les valeurs < 100)';
    }

    public function up(Schema $schema): void
    {
        // Convertir les montants dans la table invoices
        // Si les montants sont < 100, ils sont probablement en centimes et doivent être multipliés par 100
        // Exemple: 20.00 (centimes) -> 2000.00 (euros)
        $this->addSql("
            UPDATE invoices 
            SET montant_ht = ROUND(montant_ht * 100, 2)
            WHERE montant_ht < 100 AND montant_ht > 0
        ");
        
        $this->addSql("
            UPDATE invoices 
            SET montant_ttc = ROUND(montant_ttc * 100, 2)
            WHERE montant_ttc < 100 AND montant_ttc > 0
        ");
        
        $this->addSql("
            UPDATE invoices 
            SET montant_tva = ROUND(montant_tva * 100, 2)
            WHERE montant_tva < 100 AND montant_tva > 0
        ");
        
        $this->addSql("
            UPDATE invoices 
            SET montant_acompte = ROUND(montant_acompte * 100, 2)
            WHERE montant_acompte < 100 AND montant_acompte > 0
        ");
    }

    public function down(Schema $schema): void
    {
        // Convertir les montants de euros vers centimes (diviser par 100)
        // ATTENTION : Cette opération peut entraîner une perte de précision
        
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
    }
}

