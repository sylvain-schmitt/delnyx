<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter le champ company_name dans la table clients
 */
final class Version20251127200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ company_name (raison sociale) dans la table clients';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients ADD company_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients DROP company_name');
    }
}

