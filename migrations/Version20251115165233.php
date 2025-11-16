<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251115165233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE amendment_lines DROP CONSTRAINT fk_amendment_lines_amendment');
        $this->addSql('ALTER TABLE amendment_lines DROP CONSTRAINT fk_amendment_lines_tariff');
        $this->addSql('ALTER TABLE amendment_lines ALTER quantity DROP DEFAULT');
        $this->addSql('ALTER TABLE amendment_lines ADD CONSTRAINT FK_BED39D4C4DAB577D FOREIGN KEY (amendment_id) REFERENCES amendments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendment_lines ADD CONSTRAINT FK_BED39D4C92348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_amendment_lines_amendment RENAME TO IDX_BED39D4C4DAB577D');
        $this->addSql('ALTER INDEX idx_amendment_lines_tariff RENAME TO IDX_BED39D4C92348FD2');
        $this->addSql('ALTER TABLE amendments DROP date_validation');
        $this->addSql('ALTER TABLE amendments ALTER numero DROP NOT NULL');
        $this->addSql('ALTER TABLE amendments ALTER numero TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE amendments ALTER montant_ht SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE amendments ALTER montant_tva SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE amendments ALTER montant_ttc SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE amendments ALTER taux_tva SET DEFAULT \'0\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D287B73F55AE19E ON amendments (numero)');
        $this->addSql('ALTER TABLE credit_note_lines ALTER unit_price SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE credit_note_lines ALTER total_ht SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE credit_notes ALTER number DROP NOT NULL');
        $this->addSql('ALTER TABLE credit_notes ALTER reason SET NOT NULL');
        $this->addSql('ALTER TABLE credit_notes ALTER date_creation TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE credit_notes ALTER date_emission TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE credit_notes ALTER montant_ht SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE credit_notes ALTER montant_tva SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE credit_notes ALTER montant_ttc SET DEFAULT \'0\'');
        $this->addSql('COMMENT ON COLUMN credit_notes.date_creation IS NULL');
        $this->addSql('COMMENT ON COLUMN credit_notes.date_emission IS NULL');
        $this->addSql('ALTER TABLE invoice_lines ALTER unit_price SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE invoice_lines ALTER total_ht SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE invoices ADD sent_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE invoices ADD delivery_channel VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE quote_lines ALTER unit_price SET DEFAULT \'0\'');
        $this->addSql('ALTER TABLE quote_lines ALTER total_ht SET DEFAULT \'0\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE invoice_lines ALTER unit_price DROP DEFAULT');
        $this->addSql('ALTER TABLE invoice_lines ALTER total_ht DROP DEFAULT');
        $this->addSql('ALTER TABLE credit_notes ALTER number SET NOT NULL');
        $this->addSql('ALTER TABLE credit_notes ALTER reason DROP NOT NULL');
        $this->addSql('ALTER TABLE credit_notes ALTER date_creation TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE credit_notes ALTER date_emission TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE credit_notes ALTER montant_ht DROP DEFAULT');
        $this->addSql('ALTER TABLE credit_notes ALTER montant_tva DROP DEFAULT');
        $this->addSql('ALTER TABLE credit_notes ALTER montant_ttc DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN credit_notes.date_creation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN credit_notes.date_emission IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE credit_note_lines ALTER unit_price DROP DEFAULT');
        $this->addSql('ALTER TABLE credit_note_lines ALTER total_ht DROP DEFAULT');
        $this->addSql('ALTER TABLE amendment_lines DROP CONSTRAINT FK_BED39D4C4DAB577D');
        $this->addSql('ALTER TABLE amendment_lines DROP CONSTRAINT FK_BED39D4C92348FD2');
        $this->addSql('ALTER TABLE amendment_lines ALTER quantity SET DEFAULT 1');
        $this->addSql('ALTER TABLE amendment_lines ADD CONSTRAINT fk_amendment_lines_amendment FOREIGN KEY (amendment_id) REFERENCES amendments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendment_lines ADD CONSTRAINT fk_amendment_lines_tariff FOREIGN KEY (tariff_id) REFERENCES tariffs (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX idx_bed39d4c4dab577d RENAME TO idx_amendment_lines_amendment');
        $this->addSql('ALTER INDEX idx_bed39d4c92348fd2 RENAME TO idx_amendment_lines_tariff');
        $this->addSql('DROP INDEX UNIQ_D287B73F55AE19E');
        $this->addSql('ALTER TABLE amendments ADD date_validation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE amendments ALTER numero SET NOT NULL');
        $this->addSql('ALTER TABLE amendments ALTER numero TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE amendments ALTER montant_ht DROP DEFAULT');
        $this->addSql('ALTER TABLE amendments ALTER montant_tva DROP DEFAULT');
        $this->addSql('ALTER TABLE amendments ALTER montant_ttc DROP DEFAULT');
        $this->addSql('ALTER TABLE amendments ALTER taux_tva DROP DEFAULT');
        $this->addSql('ALTER TABLE invoices DROP sent_count');
        $this->addSql('ALTER TABLE invoices DROP delivery_channel');
        $this->addSql('ALTER TABLE quote_lines ALTER unit_price DROP DEFAULT');
        $this->addSql('ALTER TABLE quote_lines ALTER total_ht DROP DEFAULT');
    }
}
