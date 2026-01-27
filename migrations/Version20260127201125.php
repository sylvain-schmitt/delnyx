<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127201125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE amendments ADD invoice_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE amendments ADD credit_note_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE amendments ADD CONSTRAINT FK_D287B732989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendments ADD CONSTRAINT FK_D287B731C696F7A FOREIGN KEY (credit_note_id) REFERENCES credit_notes (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D287B732989F1FD ON amendments (invoice_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D287B731C696F7A ON amendments (credit_note_id)');
        $this->addSql('ALTER TABLE company_settings ADD iban VARCHAR(34) DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD bic VARCHAR(11) DEFAULT NULL');
        $this->addSql('ALTER TABLE email_logs ALTER type TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE invoice_lines ADD subscription_mode VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoice_lines ADD recurrence_amount NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE quote_lines ADD subscription_mode VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE quote_lines ADD recurrence_amount NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE email_logs ALTER type TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE quote_lines DROP subscription_mode');
        $this->addSql('ALTER TABLE quote_lines DROP recurrence_amount');
        $this->addSql('ALTER TABLE company_settings DROP iban');
        $this->addSql('ALTER TABLE company_settings DROP bic');
        $this->addSql('ALTER TABLE amendments DROP CONSTRAINT FK_D287B732989F1FD');
        $this->addSql('ALTER TABLE amendments DROP CONSTRAINT FK_D287B731C696F7A');
        $this->addSql('DROP INDEX UNIQ_D287B732989F1FD');
        $this->addSql('DROP INDEX UNIQ_D287B731C696F7A');
        $this->addSql('ALTER TABLE amendments DROP invoice_id');
        $this->addSql('ALTER TABLE amendments DROP credit_note_id');
        $this->addSql('ALTER TABLE invoice_lines DROP subscription_mode');
        $this->addSql('ALTER TABLE invoice_lines DROP recurrence_amount');
    }
}
