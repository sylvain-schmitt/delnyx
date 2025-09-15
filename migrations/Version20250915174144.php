<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915174144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avenants (id SERIAL NOT NULL, numero VARCHAR(20) NOT NULL, type_document VARCHAR(20) NOT NULL, document_id INT NOT NULL, document_numero VARCHAR(20) NOT NULL, motif TEXT NOT NULL, modifications TEXT NOT NULL, justification TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_validation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, statut VARCHAR(20) NOT NULL, notes TEXT DEFAULT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE avenants');
    }
}
