<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251108144103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE devis_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE factures_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE tarifs_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE avenants_id_seq CASCADE');
        $this->addSql('CREATE TABLE amendments (id SERIAL NOT NULL, quote_id INT NOT NULL, numero VARCHAR(20) NOT NULL, motif TEXT NOT NULL, modifications TEXT NOT NULL, justification TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_validation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, statut VARCHAR(20) NOT NULL, notes TEXT DEFAULT NULL, montant_ht INT NOT NULL, montant_tva INT NOT NULL, montant_ttc INT NOT NULL, taux_tva NUMERIC(5, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D287B73DB805178 ON amendments (quote_id)');
        $this->addSql('CREATE TABLE amendment_tariffs (amendment_id INT NOT NULL, tariff_id INT NOT NULL, PRIMARY KEY(amendment_id, tariff_id))');
        $this->addSql('CREATE INDEX IDX_24495B1F4DAB577D ON amendment_tariffs (amendment_id)');
        $this->addSql('CREATE INDEX IDX_24495B1F92348FD2 ON amendment_tariffs (tariff_id)');
        $this->addSql('CREATE TABLE invoices (id SERIAL NOT NULL, quote_id INT NOT NULL, client_id INT NOT NULL, numero VARCHAR(50) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_echeance TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, statut VARCHAR(20) NOT NULL, montant_ht NUMERIC(10, 2) NOT NULL, montant_tva NUMERIC(10, 2) NOT NULL, montant_ttc NUMERIC(10, 2) NOT NULL, montant_acompte NUMERIC(10, 2) DEFAULT NULL, conditions_paiement TEXT DEFAULT NULL, delai_paiement INT DEFAULT NULL, penalites_retard NUMERIC(5, 2) DEFAULT NULL, notes TEXT DEFAULT NULL, date_paiement TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_envoi TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2F2F95F55AE19E ON invoices (numero)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2F2F95DB805178 ON invoices (quote_id)');
        $this->addSql('CREATE INDEX IDX_6A2F2F9519EB6921 ON invoices (client_id)');
        $this->addSql('CREATE TABLE quotes (id SERIAL NOT NULL, client_id INT NOT NULL, numero VARCHAR(50) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_validite TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, statut VARCHAR(20) NOT NULL, montant_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, taux_tva NUMERIC(5, 2) DEFAULT \'20\' NOT NULL, montant_ttc NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, acompte_pourcentage NUMERIC(5, 2) DEFAULT \'30\' NOT NULL, conditions_paiement TEXT DEFAULT NULL, delai_livraison VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, date_acceptation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, signature_client TEXT DEFAULT NULL, siren_client VARCHAR(9) DEFAULT NULL, adresse_livraison TEXT DEFAULT NULL, type_operations VARCHAR(20) DEFAULT \'services\' NOT NULL, paiement_tva_sur_debits BOOLEAN DEFAULT false NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A1B588C5F55AE19E ON quotes (numero)');
        $this->addSql('CREATE INDEX IDX_A1B588C519EB6921 ON quotes (client_id)');
        $this->addSql('CREATE TABLE quote_tariffs (quote_id INT NOT NULL, tariff_id INT NOT NULL, PRIMARY KEY(quote_id, tariff_id))');
        $this->addSql('CREATE INDEX IDX_DAC10F2EDB805178 ON quote_tariffs (quote_id)');
        $this->addSql('CREATE INDEX IDX_DAC10F2E92348FD2 ON quote_tariffs (tariff_id)');
        $this->addSql('CREATE TABLE tariffs (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, categorie VARCHAR(50) NOT NULL, description TEXT DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, unite VARCHAR(20) DEFAULT \'forfait\' NOT NULL, actif BOOLEAN DEFAULT true NOT NULL, ordre INT DEFAULT 0 NOT NULL, caracteristiques TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE amendments ADD CONSTRAINT FK_D287B73DB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendment_tariffs ADD CONSTRAINT FK_24495B1F4DAB577D FOREIGN KEY (amendment_id) REFERENCES amendments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendment_tariffs ADD CONSTRAINT FK_24495B1F92348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F95DB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F9519EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quotes ADD CONSTRAINT FK_A1B588C519EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote_tariffs ADD CONSTRAINT FK_DAC10F2EDB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote_tariffs ADD CONSTRAINT FK_DAC10F2E92348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis_tarifs DROP CONSTRAINT fk_c5ad035a357c0a59');
        $this->addSql('ALTER TABLE devis_tarifs DROP CONSTRAINT fk_c5ad035a41defada');
        $this->addSql('ALTER TABLE factures DROP CONSTRAINT fk_647590b19eb6921');
        $this->addSql('ALTER TABLE factures DROP CONSTRAINT fk_647590b41defada');
        $this->addSql('ALTER TABLE avenant_tarifs DROP CONSTRAINT fk_7752449e357c0a59');
        $this->addSql('ALTER TABLE avenant_tarifs DROP CONSTRAINT fk_7752449e85631a3a');
        $this->addSql('ALTER TABLE devis DROP CONSTRAINT fk_8b27c52b19eb6921');
        $this->addSql('ALTER TABLE avenants DROP CONSTRAINT fk_cb6c27a041defada');
        $this->addSql('DROP TABLE devis_tarifs');
        $this->addSql('DROP TABLE factures');
        $this->addSql('DROP TABLE avenant_tarifs');
        $this->addSql('DROP TABLE devis');
        $this->addSql('DROP TABLE tarifs');
        $this->addSql('DROP TABLE avenants');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE devis_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE factures_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE tarifs_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE avenants_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE devis_tarifs (devis_id INT NOT NULL, tarif_id INT NOT NULL, PRIMARY KEY(devis_id, tarif_id))');
        $this->addSql('CREATE INDEX idx_c5ad035a357c0a59 ON devis_tarifs (tarif_id)');
        $this->addSql('CREATE INDEX idx_c5ad035a41defada ON devis_tarifs (devis_id)');
        $this->addSql('CREATE TABLE factures (id SERIAL NOT NULL, devis_id INT NOT NULL, client_id INT NOT NULL, numero VARCHAR(50) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_echeance TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, statut VARCHAR(20) NOT NULL, montant_ht NUMERIC(10, 2) NOT NULL, montant_tva NUMERIC(10, 2) NOT NULL, montant_ttc NUMERIC(10, 2) NOT NULL, montant_acompte NUMERIC(10, 2) DEFAULT NULL, conditions_paiement TEXT DEFAULT NULL, delai_paiement INT DEFAULT NULL, penalites_retard NUMERIC(5, 2) DEFAULT NULL, notes TEXT DEFAULT NULL, date_paiement TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_envoi TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_647590b19eb6921 ON factures (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_647590b41defada ON factures (devis_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_647590bf55ae19e ON factures (numero)');
        $this->addSql('CREATE TABLE avenant_tarifs (avenant_id INT NOT NULL, tarif_id INT NOT NULL, PRIMARY KEY(avenant_id, tarif_id))');
        $this->addSql('CREATE INDEX idx_7752449e357c0a59 ON avenant_tarifs (tarif_id)');
        $this->addSql('CREATE INDEX idx_7752449e85631a3a ON avenant_tarifs (avenant_id)');
        $this->addSql('CREATE TABLE devis (id SERIAL NOT NULL, client_id INT NOT NULL, numero VARCHAR(50) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_validite TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, statut VARCHAR(20) NOT NULL, montant_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, taux_tva NUMERIC(5, 2) DEFAULT \'20\' NOT NULL, montant_ttc NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, acompte_pourcentage NUMERIC(5, 2) DEFAULT \'30\' NOT NULL, conditions_paiement TEXT DEFAULT NULL, delai_livraison VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, date_acceptation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, signature_client TEXT DEFAULT NULL, siren_client VARCHAR(9) DEFAULT NULL, adresse_livraison TEXT DEFAULT NULL, type_operations VARCHAR(20) DEFAULT \'services\' NOT NULL, paiement_tva_sur_debits BOOLEAN DEFAULT false NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_8b27c52b19eb6921 ON devis (client_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_8b27c52bf55ae19e ON devis (numero)');
        $this->addSql('CREATE TABLE tarifs (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, categorie VARCHAR(50) NOT NULL, description TEXT DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, unite VARCHAR(20) DEFAULT \'forfait\' NOT NULL, actif BOOLEAN DEFAULT true NOT NULL, ordre INT DEFAULT 0 NOT NULL, caracteristiques TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE avenants (id SERIAL NOT NULL, devis_id INT NOT NULL, numero VARCHAR(20) NOT NULL, motif TEXT NOT NULL, modifications TEXT NOT NULL, justification TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_validation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, statut VARCHAR(20) NOT NULL, notes TEXT DEFAULT NULL, montant_ht INT NOT NULL, montant_tva INT NOT NULL, montant_ttc INT NOT NULL, taux_tva NUMERIC(5, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_cb6c27a041defada ON avenants (devis_id)');
        $this->addSql('ALTER TABLE devis_tarifs ADD CONSTRAINT fk_c5ad035a357c0a59 FOREIGN KEY (tarif_id) REFERENCES tarifs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis_tarifs ADD CONSTRAINT fk_c5ad035a41defada FOREIGN KEY (devis_id) REFERENCES devis (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE factures ADD CONSTRAINT fk_647590b19eb6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE factures ADD CONSTRAINT fk_647590b41defada FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE avenant_tarifs ADD CONSTRAINT fk_7752449e357c0a59 FOREIGN KEY (tarif_id) REFERENCES tarifs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE avenant_tarifs ADD CONSTRAINT fk_7752449e85631a3a FOREIGN KEY (avenant_id) REFERENCES avenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE devis ADD CONSTRAINT fk_8b27c52b19eb6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE avenants ADD CONSTRAINT fk_cb6c27a041defada FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendments DROP CONSTRAINT FK_D287B73DB805178');
        $this->addSql('ALTER TABLE amendment_tariffs DROP CONSTRAINT FK_24495B1F4DAB577D');
        $this->addSql('ALTER TABLE amendment_tariffs DROP CONSTRAINT FK_24495B1F92348FD2');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F95DB805178');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F9519EB6921');
        $this->addSql('ALTER TABLE quotes DROP CONSTRAINT FK_A1B588C519EB6921');
        $this->addSql('ALTER TABLE quote_tariffs DROP CONSTRAINT FK_DAC10F2EDB805178');
        $this->addSql('ALTER TABLE quote_tariffs DROP CONSTRAINT FK_DAC10F2E92348FD2');
        $this->addSql('DROP TABLE amendments');
        $this->addSql('DROP TABLE amendment_tariffs');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE quotes');
        $this->addSql('DROP TABLE quote_tariffs');
        $this->addSql('DROP TABLE tariffs');
    }
}
