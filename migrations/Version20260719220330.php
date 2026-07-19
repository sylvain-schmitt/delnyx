<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260719220330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE purchase_invoices ADD invoice_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_invoices ADD lines TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_invoices ADD vat_breakdown TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_invoices ADD local_pdf_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE purchase_invoices DROP invoice_number');
        $this->addSql('ALTER TABLE purchase_invoices DROP lines');
        $this->addSql('ALTER TABLE purchase_invoices DROP vat_breakdown');
        $this->addSql('ALTER TABLE purchase_invoices DROP local_pdf_path');
    }
}
