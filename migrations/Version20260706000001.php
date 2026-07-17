<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de pdp_client_id sur company_settings (OAuth client_credentials Super PDP)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_settings ADD COLUMN pdp_client_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE company_settings DROP COLUMN pdp_client_id');
    }
}
