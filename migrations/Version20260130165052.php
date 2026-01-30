<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130165052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company_settings ADD google_place_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD google_api_key TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD google_reviews_enabled BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE company_settings DROP google_place_id');
        $this->addSql('ALTER TABLE company_settings DROP google_api_key');
        $this->addSql('ALTER TABLE company_settings DROP google_reviews_enabled');
    }
}
