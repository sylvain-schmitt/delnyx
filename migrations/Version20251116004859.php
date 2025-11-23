<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251116004859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE amendments ADD sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE amendments ADD sent_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE credit_notes ADD sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_notes ADD sent_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE amendments DROP sent_at');
        $this->addSql('ALTER TABLE amendments DROP sent_count');
        $this->addSql('ALTER TABLE credit_notes DROP sent_at');
        $this->addSql('ALTER TABLE credit_notes DROP sent_count');
    }
}
