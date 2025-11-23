<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112173829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rendre le champ quote_id nullable dans la table invoices pour permettre la création de factures sans devis associé';
    }

    public function up(Schema $schema): void
    {
        // Rendre le champ quote_id nullable pour permettre les factures sans devis
        $this->addSql('ALTER TABLE invoices ALTER quote_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remettre le champ quote_id en NOT NULL (attention : cela peut échouer si des factures sans devis existent)
        $this->addSql('ALTER TABLE invoices ALTER quote_id SET NOT NULL');
    }
}
