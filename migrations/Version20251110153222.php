<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110153222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ avatar_path à l\'entité User pour la photo de profil';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD avatar_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP avatar_path');
    }
}
