<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131141547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company_settings ADD google_calendar_enabled BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE company_settings ADD google_client_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD google_client_secret TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD google_calendar_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD google_oauth_access_token TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD google_oauth_refresh_token TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD google_oauth_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN company_settings.google_oauth_token_expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE company_settings DROP google_calendar_enabled');
        $this->addSql('ALTER TABLE company_settings DROP google_client_id');
        $this->addSql('ALTER TABLE company_settings DROP google_client_secret');
        $this->addSql('ALTER TABLE company_settings DROP google_calendar_id');
        $this->addSql('ALTER TABLE company_settings DROP google_oauth_access_token');
        $this->addSql('ALTER TABLE company_settings DROP google_oauth_refresh_token');
        $this->addSql('ALTER TABLE company_settings DROP google_oauth_token_expires_at');
    }
}
