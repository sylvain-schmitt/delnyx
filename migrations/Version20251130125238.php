<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251130125238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment (id SERIAL NOT NULL, invoice_id INT NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, provider VARCHAR(20) NOT NULL, provider_payment_id VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, refunded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, failure_reason TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_payment_invoice ON payment (invoice_id)');
        $this->addSql('CREATE INDEX idx_payment_status ON payment (status)');
        $this->addSql('CREATE INDEX idx_payment_date ON payment (created_at)');
        $this->addSql('COMMENT ON COLUMN payment.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payment.paid_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payment.refunded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE signature (id SERIAL NOT NULL, document_type VARCHAR(20) NOT NULL, document_id INT NOT NULL, signer_name VARCHAR(255) NOT NULL, signer_email VARCHAR(255) NOT NULL, signature_method VARCHAR(20) NOT NULL, signature_data JSON NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent TEXT DEFAULT NULL, signed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, document_hash VARCHAR(64) DEFAULT NULL, metadata JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_signature_document ON signature (document_type, document_id)');
        $this->addSql('CREATE INDEX idx_signature_date ON signature (signed_at)');
        $this->addSql('COMMENT ON COLUMN signature.signed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE payment DROP CONSTRAINT FK_6D28840D2989F1FD');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE signature');
    }
}
