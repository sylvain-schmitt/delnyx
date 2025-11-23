<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110150113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rendre l\'email optionnel dans CompanySettings (utilise l\'email du User en fallback)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_settings ALTER email DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Note: Cette migration peut échouer si des valeurs NULL existent
        $this->addSql('ALTER TABLE company_settings ALTER email SET NOT NULL');
    }
}
