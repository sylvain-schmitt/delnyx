<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719183725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE purchase_invoices (id SERIAL NOT NULL, company_id VARCHAR(36) NOT NULL, pdp_invoice_id INT NOT NULL, external_id VARCHAR(100) DEFAULT NULL, seller_name VARCHAR(255) DEFAULT NULL, seller_siren VARCHAR(20) DEFAULT NULL, buyer_name VARCHAR(255) DEFAULT NULL, total_ht NUMERIC(10, 2) DEFAULT NULL, total_ttc NUMERIC(10, 2) DEFAULT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, issue_date DATE DEFAULT NULL, pdp_status VARCHAR(20) DEFAULT NULL, pdp_raw_response TEXT DEFAULT NULL, local_status VARCHAR(20) DEFAULT NULL, acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, rejected_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, rejection_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE credit_notes ADD e_invoicing_mode VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_notes ADD pdp_status VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_notes ADD pdp_provider VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_notes ADD pdp_transmission_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_notes ADD pdp_response TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE purchase_invoices');
        $this->addSql('ALTER TABLE credit_notes DROP e_invoicing_mode');
        $this->addSql('ALTER TABLE credit_notes DROP pdp_status');
        $this->addSql('ALTER TABLE credit_notes DROP pdp_provider');
        $this->addSql('ALTER TABLE credit_notes DROP pdp_transmission_date');
        $this->addSql('ALTER TABLE credit_notes DROP pdp_response');
    }
}
