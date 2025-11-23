<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251116132345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ delivery_channel aux tables amendments et credit_notes pour suivre le canal de livraison';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE amendments ADD delivery_channel VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_notes ADD delivery_channel VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_notes DROP delivery_channel');
        $this->addSql('ALTER TABLE amendments DROP delivery_channel');
    }
}
