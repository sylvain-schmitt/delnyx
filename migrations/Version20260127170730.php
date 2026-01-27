<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127170730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company_settings ADD stripe_secret_key TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD stripe_publishable_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD stripe_webhook_secret TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD stripe_enabled BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE company_settings DROP stripe_secret_key');
        $this->addSql('ALTER TABLE company_settings DROP stripe_publishable_key');
        $this->addSql('ALTER TABLE company_settings DROP stripe_webhook_secret');
        $this->addSql('ALTER TABLE company_settings DROP stripe_enabled');
    }
}
