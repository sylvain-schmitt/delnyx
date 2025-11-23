<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110131956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE credit_note_lines (id SERIAL NOT NULL, credit_note_id INT NOT NULL, tariff_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, total_ht INT NOT NULL, tva_rate NUMERIC(5, 2) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D96994FF1C696F7A ON credit_note_lines (credit_note_id)');
        $this->addSql('CREATE INDEX IDX_D96994FF92348FD2 ON credit_note_lines (tariff_id)');
        $this->addSql('CREATE TABLE credit_notes (id SERIAL NOT NULL, invoice_id INT NOT NULL, number VARCHAR(30) NOT NULL, status VARCHAR(20) NOT NULL, reason TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_emission TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, montant_ht INT NOT NULL, montant_tva INT NOT NULL, montant_ttc INT NOT NULL, company_id VARCHAR(36) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5974282296901F54 ON credit_notes (number)');
        $this->addSql('CREATE INDEX IDX_597428222989F1FD ON credit_notes (invoice_id)');
        $this->addSql('COMMENT ON COLUMN credit_notes.date_creation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN credit_notes.date_emission IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE invoice_lines (id SERIAL NOT NULL, invoice_id INT NOT NULL, tariff_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, total_ht INT NOT NULL, tva_rate NUMERIC(5, 2) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_72DBDC232989F1FD ON invoice_lines (invoice_id)');
        $this->addSql('CREATE INDEX IDX_72DBDC2392348FD2 ON invoice_lines (tariff_id)');
        $this->addSql('CREATE TABLE quote_lines (id SERIAL NOT NULL, quote_id INT NOT NULL, tariff_id INT DEFAULT NULL, description VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, total_ht INT NOT NULL, tva_rate NUMERIC(5, 2) DEFAULT NULL, is_custom BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_42FE01F7DB805178 ON quote_lines (quote_id)');
        $this->addSql('CREATE INDEX IDX_42FE01F792348FD2 ON quote_lines (tariff_id)');
        $this->addSql('ALTER TABLE credit_note_lines ADD CONSTRAINT FK_D96994FF1C696F7A FOREIGN KEY (credit_note_id) REFERENCES credit_notes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE credit_note_lines ADD CONSTRAINT FK_D96994FF92348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE credit_notes ADD CONSTRAINT FK_597428222989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT FK_72DBDC232989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT FK_72DBDC2392348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote_lines ADD CONSTRAINT FK_42FE01F7DB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote_lines ADD CONSTRAINT FK_42FE01F792348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendments ADD company_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE invoices ADD company_id VARCHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE quotes ADD company_id VARCHAR(36) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE credit_note_lines DROP CONSTRAINT FK_D96994FF1C696F7A');
        $this->addSql('ALTER TABLE credit_note_lines DROP CONSTRAINT FK_D96994FF92348FD2');
        $this->addSql('ALTER TABLE credit_notes DROP CONSTRAINT FK_597428222989F1FD');
        $this->addSql('ALTER TABLE invoice_lines DROP CONSTRAINT FK_72DBDC232989F1FD');
        $this->addSql('ALTER TABLE invoice_lines DROP CONSTRAINT FK_72DBDC2392348FD2');
        $this->addSql('ALTER TABLE quote_lines DROP CONSTRAINT FK_42FE01F7DB805178');
        $this->addSql('ALTER TABLE quote_lines DROP CONSTRAINT FK_42FE01F792348FD2');
        $this->addSql('DROP TABLE credit_note_lines');
        $this->addSql('DROP TABLE credit_notes');
        $this->addSql('DROP TABLE invoice_lines');
        $this->addSql('DROP TABLE quote_lines');
        $this->addSql('ALTER TABLE amendments DROP company_id');
        $this->addSql('ALTER TABLE invoices DROP company_id');
        $this->addSql('ALTER TABLE quotes DROP company_id');
    }
}
