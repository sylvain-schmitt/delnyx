<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de pdp_api_endpoint et pdp_webhook_secret sur company_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_settings ADD COLUMN pdp_api_endpoint TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD COLUMN pdp_webhook_secret TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_settings DROP COLUMN pdp_api_endpoint');
        $this->addSql('ALTER TABLE company_settings DROP COLUMN pdp_webhook_secret');
    }
}
