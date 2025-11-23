<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122171733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE amendments ADD pdf_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE amendments ADD pdf_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD pdf_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD pdf_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE quotes ADD pdf_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE quotes ADD pdf_hash VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE quotes DROP pdf_filename');
        $this->addSql('ALTER TABLE quotes DROP pdf_hash');
        $this->addSql('ALTER TABLE invoices DROP pdf_filename');
        $this->addSql('ALTER TABLE invoices DROP pdf_hash');
        $this->addSql('ALTER TABLE amendments DROP pdf_filename');
        $this->addSql('ALTER TABLE amendments DROP pdf_hash');
    }
}
