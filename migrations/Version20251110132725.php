<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110132725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE company_settings (id SERIAL NOT NULL, company_id VARCHAR(36) NOT NULL, tva_enabled BOOLEAN DEFAULT false NOT NULL, taux_tvadefaut NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, pdp_mode VARCHAR(20) DEFAULT \'none\' NOT NULL, pdp_provider VARCHAR(100) DEFAULT NULL, pdp_api_key TEXT DEFAULT NULL, pdp_status VARCHAR(50) DEFAULT NULL, siren VARCHAR(9) DEFAULT NULL, siret VARCHAR(14) DEFAULT NULL, raison_sociale VARCHAR(255) NOT NULL, adresse TEXT NOT NULL, code_postal VARCHAR(10) NOT NULL, ville VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, signature_provider VARCHAR(50) DEFAULT NULL, signature_api_key TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FDD2B5A8979B1AD6 ON company_settings (company_id)');
        $this->addSql('ALTER TABLE quotes ADD date_signature TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE company_settings');
        $this->addSql('ALTER TABLE quotes DROP date_signature');
    }
}
