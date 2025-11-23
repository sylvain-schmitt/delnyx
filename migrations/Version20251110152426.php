<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110152426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs prénom et nom à l\'entité User pour les documents commerciaux';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD prenom VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD nom VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP prenom');
        $this->addSql('ALTER TABLE "user" DROP nom');
    }
}
