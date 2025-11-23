<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123194552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE amendment_lines (id SERIAL NOT NULL, amendment_id INT NOT NULL, tariff_id INT DEFAULT NULL, source_line_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, total_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, tva_rate NUMERIC(5, 2) DEFAULT NULL, old_value NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, new_value NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, delta NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BED39D4C4DAB577D ON amendment_lines (amendment_id)');
        $this->addSql('CREATE INDEX IDX_BED39D4C92348FD2 ON amendment_lines (tariff_id)');
        $this->addSql('CREATE INDEX IDX_BED39D4C4A4CD2A ON amendment_lines (source_line_id)');
        $this->addSql('CREATE TABLE amendments (id SERIAL NOT NULL, quote_id INT NOT NULL, numero VARCHAR(50) DEFAULT NULL, motif TEXT NOT NULL, modifications TEXT NOT NULL, justification TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_signature TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent_count INT DEFAULT 0 NOT NULL, delivery_channel VARCHAR(20) DEFAULT NULL, signature_client TEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, notes TEXT DEFAULT NULL, company_id VARCHAR(36) NOT NULL, pdf_filename VARCHAR(255) DEFAULT NULL, pdf_hash VARCHAR(64) DEFAULT NULL, montant_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, montant_tva NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, montant_ttc NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, taux_tva NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D287B73F55AE19E ON amendments (numero)');
        $this->addSql('CREATE INDEX IDX_D287B73DB805178 ON amendments (quote_id)');
        $this->addSql('CREATE TABLE audit_logs (id SERIAL NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT NOT NULL, action VARCHAR(50) NOT NULL, old_value JSON DEFAULT NULL, new_value JSON DEFAULT NULL, metadata JSON DEFAULT NULL, user_id INT DEFAULT NULL, user_email VARCHAR(255) DEFAULT NULL, document_hash VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_entity ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_user ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX idx_created_at ON audit_logs (created_at)');
        $this->addSql('CREATE TABLE clients (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, pays VARCHAR(100) DEFAULT \'France\' NOT NULL, siret VARCHAR(14) DEFAULT NULL, tva_intracommunautaire VARCHAR(20) DEFAULT NULL, statut VARCHAR(255) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, notes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C82E74E7927C74 ON clients (email)');
        $this->addSql('CREATE TABLE company_settings (id SERIAL NOT NULL, company_id VARCHAR(36) NOT NULL, tva_enabled BOOLEAN DEFAULT false NOT NULL, taux_tvadefaut NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, pdp_mode VARCHAR(20) DEFAULT \'none\' NOT NULL, pdp_provider VARCHAR(100) DEFAULT NULL, pdp_api_key TEXT DEFAULT NULL, pdp_status VARCHAR(50) DEFAULT NULL, siren VARCHAR(9) DEFAULT NULL, siret VARCHAR(14) DEFAULT NULL, raison_sociale VARCHAR(255) NOT NULL, adresse TEXT NOT NULL, code_postal VARCHAR(10) NOT NULL, ville VARCHAR(100) NOT NULL, email VARCHAR(255) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, signature_provider VARCHAR(50) DEFAULT NULL, signature_api_key TEXT DEFAULT NULL, logo_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FDD2B5A8979B1AD6 ON company_settings (company_id)');
        $this->addSql('CREATE TABLE credit_note_lines (id SERIAL NOT NULL, credit_note_id INT NOT NULL, tariff_id INT DEFAULT NULL, source_line_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, total_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, tva_rate NUMERIC(5, 2) DEFAULT NULL, old_value NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, new_value NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, delta NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D96994FF1C696F7A ON credit_note_lines (credit_note_id)');
        $this->addSql('CREATE INDEX IDX_D96994FF92348FD2 ON credit_note_lines (tariff_id)');
        $this->addSql('CREATE INDEX IDX_D96994FF4A4CD2A ON credit_note_lines (source_line_id)');
        $this->addSql('CREATE TABLE credit_notes (id SERIAL NOT NULL, invoice_id INT NOT NULL, number VARCHAR(30) DEFAULT NULL, status VARCHAR(20) NOT NULL, reason TEXT NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_emission TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent_count INT DEFAULT 0 NOT NULL, delivery_channel VARCHAR(20) DEFAULT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, montant_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, montant_tva NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, montant_ttc NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, taux_tva NUMERIC(5, 2) DEFAULT NULL, company_id VARCHAR(36) NOT NULL, pdf_filename VARCHAR(255) DEFAULT NULL, pdf_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5974282296901F54 ON credit_notes (number)');
        $this->addSql('CREATE INDEX IDX_597428222989F1FD ON credit_notes (invoice_id)');
        $this->addSql('CREATE TABLE email_logs (id SERIAL NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT NOT NULL, recipient VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, error_message TEXT DEFAULT NULL, metadata JSON DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id INT DEFAULT NULL, user_email VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_email_entity ON email_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_email_sent_at ON email_logs (sent_at)');
        $this->addSql('CREATE INDEX idx_email_status ON email_logs (status)');
        $this->addSql('COMMENT ON COLUMN email_logs.sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE invoice_lines (id SERIAL NOT NULL, invoice_id INT NOT NULL, tariff_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, total_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, tva_rate NUMERIC(5, 2) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_72DBDC232989F1FD ON invoice_lines (invoice_id)');
        $this->addSql('CREATE INDEX IDX_72DBDC2392348FD2 ON invoice_lines (tariff_id)');
        $this->addSql('CREATE TABLE invoices (id SERIAL NOT NULL, quote_id INT DEFAULT NULL, client_id INT NOT NULL, numero VARCHAR(50) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_echeance TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, statut VARCHAR(20) NOT NULL, montant_ht NUMERIC(10, 2) NOT NULL, montant_tva NUMERIC(10, 2) NOT NULL, montant_ttc NUMERIC(10, 2) NOT NULL, montant_acompte NUMERIC(10, 2) DEFAULT NULL, conditions_paiement TEXT DEFAULT NULL, delai_paiement INT DEFAULT NULL, penalites_retard NUMERIC(5, 2) DEFAULT NULL, notes TEXT DEFAULT NULL, date_paiement TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_envoi TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent_count INT DEFAULT 0 NOT NULL, delivery_channel VARCHAR(20) DEFAULT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, company_id VARCHAR(36) NOT NULL, pdf_filename VARCHAR(255) DEFAULT NULL, pdf_hash VARCHAR(64) DEFAULT NULL, pdp_status VARCHAR(50) DEFAULT NULL, pdp_provider VARCHAR(100) DEFAULT NULL, pdp_transmission_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, pdp_response TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2F2F95F55AE19E ON invoices (numero)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2F2F95DB805178 ON invoices (quote_id)');
        $this->addSql('CREATE INDEX IDX_6A2F2F9519EB6921 ON invoices (client_id)');
        $this->addSql('CREATE TABLE project (id SERIAL NOT NULL, titre VARCHAR(200) NOT NULL, description TEXT NOT NULL, url VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN project.date_creation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN project.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE project_technology (project_id INT NOT NULL, technology_id INT NOT NULL, PRIMARY KEY(project_id, technology_id))');
        $this->addSql('CREATE INDEX IDX_ECC5297F166D1F9C ON project_technology (project_id)');
        $this->addSql('CREATE INDEX IDX_ECC5297F4235D463 ON project_technology (technology_id)');
        $this->addSql('CREATE TABLE project_image (id SERIAL NOT NULL, projet_id INT NOT NULL, fichier VARCHAR(255) NOT NULL, alt_text VARCHAR(255) DEFAULT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D6680DC1C18272 ON project_image (projet_id)');
        $this->addSql('COMMENT ON COLUMN project_image.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN project_image.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE quote_lines (id SERIAL NOT NULL, quote_id INT NOT NULL, tariff_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, total_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, tva_rate NUMERIC(5, 2) DEFAULT NULL, is_custom BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_42FE01F7DB805178 ON quote_lines (quote_id)');
        $this->addSql('CREATE INDEX IDX_42FE01F792348FD2 ON quote_lines (tariff_id)');
        $this->addSql('CREATE TABLE quotes (id SERIAL NOT NULL, client_id INT NOT NULL, numero VARCHAR(50) DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_validite TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, statut VARCHAR(20) NOT NULL, montant_ht NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, taux_tva NUMERIC(5, 2) DEFAULT \'0\' NOT NULL, use_per_line_tva BOOLEAN DEFAULT false NOT NULL, montant_ttc NUMERIC(10, 2) DEFAULT \'0\' NOT NULL, acompte_pourcentage NUMERIC(5, 2) DEFAULT \'30\' NOT NULL, conditions_paiement TEXT DEFAULT NULL, delai_livraison VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, date_acceptation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, signature_client TEXT DEFAULT NULL, date_signature TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_envoi TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, sent_count INT DEFAULT 0 NOT NULL, delivery_channel VARCHAR(20) DEFAULT NULL, siren_client VARCHAR(9) DEFAULT NULL, adresse_livraison TEXT DEFAULT NULL, type_operations VARCHAR(20) DEFAULT \'services\' NOT NULL, paiement_tva_sur_debits BOOLEAN DEFAULT false NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, company_id VARCHAR(36) NOT NULL, pdf_filename VARCHAR(255) DEFAULT NULL, pdf_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A1B588C5F55AE19E ON quotes (numero)');
        $this->addSql('CREATE INDEX IDX_A1B588C519EB6921 ON quotes (client_id)');
        $this->addSql('CREATE TABLE tariffs (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, categorie VARCHAR(50) NOT NULL, description TEXT DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, unite VARCHAR(20) DEFAULT \'forfait\' NOT NULL, actif BOOLEAN DEFAULT true NOT NULL, ordre INT DEFAULT 0 NOT NULL, caracteristiques TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_modification TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE technology (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, couleur VARCHAR(7) NOT NULL, icone VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN technology.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN technology.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, prenom VARCHAR(100) DEFAULT NULL, nom VARCHAR(100) DEFAULT NULL, avatar_path VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "user" (email)');
        $this->addSql('ALTER TABLE amendment_lines ADD CONSTRAINT FK_BED39D4C4DAB577D FOREIGN KEY (amendment_id) REFERENCES amendments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendment_lines ADD CONSTRAINT FK_BED39D4C92348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendment_lines ADD CONSTRAINT FK_BED39D4C4A4CD2A FOREIGN KEY (source_line_id) REFERENCES quote_lines (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendments ADD CONSTRAINT FK_D287B73DB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE credit_note_lines ADD CONSTRAINT FK_D96994FF1C696F7A FOREIGN KEY (credit_note_id) REFERENCES credit_notes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE credit_note_lines ADD CONSTRAINT FK_D96994FF92348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE credit_note_lines ADD CONSTRAINT FK_D96994FF4A4CD2A FOREIGN KEY (source_line_id) REFERENCES invoice_lines (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE credit_notes ADD CONSTRAINT FK_597428222989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT FK_72DBDC232989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT FK_72DBDC2392348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F95DB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F9519EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_technology ADD CONSTRAINT FK_ECC5297F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_technology ADD CONSTRAINT FK_ECC5297F4235D463 FOREIGN KEY (technology_id) REFERENCES technology (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_image ADD CONSTRAINT FK_D6680DC1C18272 FOREIGN KEY (projet_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote_lines ADD CONSTRAINT FK_42FE01F7DB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote_lines ADD CONSTRAINT FK_42FE01F792348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quotes ADD CONSTRAINT FK_A1B588C519EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE amendment_lines DROP CONSTRAINT FK_BED39D4C4DAB577D');
        $this->addSql('ALTER TABLE amendment_lines DROP CONSTRAINT FK_BED39D4C92348FD2');
        $this->addSql('ALTER TABLE amendment_lines DROP CONSTRAINT FK_BED39D4C4A4CD2A');
        $this->addSql('ALTER TABLE amendments DROP CONSTRAINT FK_D287B73DB805178');
        $this->addSql('ALTER TABLE credit_note_lines DROP CONSTRAINT FK_D96994FF1C696F7A');
        $this->addSql('ALTER TABLE credit_note_lines DROP CONSTRAINT FK_D96994FF92348FD2');
        $this->addSql('ALTER TABLE credit_note_lines DROP CONSTRAINT FK_D96994FF4A4CD2A');
        $this->addSql('ALTER TABLE credit_notes DROP CONSTRAINT FK_597428222989F1FD');
        $this->addSql('ALTER TABLE invoice_lines DROP CONSTRAINT FK_72DBDC232989F1FD');
        $this->addSql('ALTER TABLE invoice_lines DROP CONSTRAINT FK_72DBDC2392348FD2');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F95DB805178');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F9519EB6921');
        $this->addSql('ALTER TABLE project_technology DROP CONSTRAINT FK_ECC5297F166D1F9C');
        $this->addSql('ALTER TABLE project_technology DROP CONSTRAINT FK_ECC5297F4235D463');
        $this->addSql('ALTER TABLE project_image DROP CONSTRAINT FK_D6680DC1C18272');
        $this->addSql('ALTER TABLE quote_lines DROP CONSTRAINT FK_42FE01F7DB805178');
        $this->addSql('ALTER TABLE quote_lines DROP CONSTRAINT FK_42FE01F792348FD2');
        $this->addSql('ALTER TABLE quotes DROP CONSTRAINT FK_A1B588C519EB6921');
        $this->addSql('DROP TABLE amendment_lines');
        $this->addSql('DROP TABLE amendments');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP TABLE company_settings');
        $this->addSql('DROP TABLE credit_note_lines');
        $this->addSql('DROP TABLE credit_notes');
        $this->addSql('DROP TABLE email_logs');
        $this->addSql('DROP TABLE invoice_lines');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_technology');
        $this->addSql('DROP TABLE project_image');
        $this->addSql('DROP TABLE quote_lines');
        $this->addSql('DROP TABLE quotes');
        $this->addSql('DROP TABLE tariffs');
        $this->addSql('DROP TABLE technology');
        $this->addSql('DROP TABLE "user"');
    }
}
