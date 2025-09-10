<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910161900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE factures ADD numero VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE factures ADD date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE factures ADD date_echeance TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE factures ADD statut VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE factures ADD montant_ht NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE factures ADD montant_tva NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE factures ADD montant_ttc NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE factures ADD montant_acompte NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD conditions_paiement TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD delai_paiement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD penalites_retard NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD notes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD date_paiement TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD date_envoi TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE factures ADD date_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_647590BF55AE19E ON factures (numero)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX UNIQ_647590BF55AE19E');
        $this->addSql('ALTER TABLE factures DROP numero');
        $this->addSql('ALTER TABLE factures DROP date_creation');
        $this->addSql('ALTER TABLE factures DROP date_echeance');
        $this->addSql('ALTER TABLE factures DROP statut');
        $this->addSql('ALTER TABLE factures DROP montant_ht');
        $this->addSql('ALTER TABLE factures DROP montant_tva');
        $this->addSql('ALTER TABLE factures DROP montant_ttc');
        $this->addSql('ALTER TABLE factures DROP montant_acompte');
        $this->addSql('ALTER TABLE factures DROP conditions_paiement');
        $this->addSql('ALTER TABLE factures DROP delai_paiement');
        $this->addSql('ALTER TABLE factures DROP penalites_retard');
        $this->addSql('ALTER TABLE factures DROP notes');
        $this->addSql('ALTER TABLE factures DROP date_paiement');
        $this->addSql('ALTER TABLE factures DROP date_envoi');
        $this->addSql('ALTER TABLE factures DROP date_modification');
    }
}
