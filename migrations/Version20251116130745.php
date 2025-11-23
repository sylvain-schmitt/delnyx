<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251116130745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs date_envoi, sent_count et delivery_channel à la table quotes pour suivre l\'envoi des devis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quotes ADD date_envoi TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE quotes ADD sent_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE quotes ADD delivery_channel VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quotes DROP date_envoi');
        $this->addSql('ALTER TABLE quotes DROP sent_count');
        $this->addSql('ALTER TABLE quotes DROP delivery_channel');
    }
}
