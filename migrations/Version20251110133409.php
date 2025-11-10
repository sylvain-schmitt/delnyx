<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110133409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoices ADD pdp_status VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD pdp_provider VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD pdp_transmission_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD pdp_response TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE invoices DROP pdp_status');
        $this->addSql('ALTER TABLE invoices DROP pdp_provider');
        $this->addSql('ALTER TABLE invoices DROP pdp_transmission_date');
        $this->addSql('ALTER TABLE invoices DROP pdp_response');
    }
}
