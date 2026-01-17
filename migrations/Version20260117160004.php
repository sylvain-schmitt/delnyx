<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117160004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reminder (id SERIAL NOT NULL, invoice_id INT NOT NULL, rule_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(20) NOT NULL, error_message TEXT DEFAULT NULL, email_to VARCHAR(255) DEFAULT NULL, email_subject VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_40374F402989F1FD ON reminder (invoice_id)');
        $this->addSql('CREATE INDEX IDX_40374F40744E0351 ON reminder (rule_id)');
        $this->addSql('CREATE INDEX idx_reminder_invoice_rule ON reminder (invoice_id, rule_id)');
        $this->addSql('CREATE INDEX idx_reminder_sent_at ON reminder (sent_at)');
        $this->addSql('CREATE TABLE reminder_rule (id SERIAL NOT NULL, name VARCHAR(100) NOT NULL, days_after_due INT NOT NULL, document_type VARCHAR(20) NOT NULL, email_subject VARCHAR(255) NOT NULL, email_template TEXT NOT NULL, is_active BOOLEAN NOT NULL, ordre INT NOT NULL, max_reminders INT NOT NULL, company_id VARCHAR(36) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_reminder_rule_company_active ON reminder_rule (company_id, is_active)');
        $this->addSql('ALTER TABLE reminder ADD CONSTRAINT FK_40374F402989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reminder ADD CONSTRAINT FK_40374F40744E0351 FOREIGN KEY (rule_id) REFERENCES reminder_rule (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE reminder DROP CONSTRAINT FK_40374F402989F1FD');
        $this->addSql('ALTER TABLE reminder DROP CONSTRAINT FK_40374F40744E0351');
        $this->addSql('DROP TABLE reminder');
        $this->addSql('DROP TABLE reminder_rule');
    }
}
