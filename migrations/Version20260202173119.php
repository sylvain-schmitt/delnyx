<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260202173119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification_reads (id SERIAL NOT NULL, user_id INT NOT NULL, notification_key VARCHAR(100) NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_notification_user ON notification_reads (user_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_user_notification ON notification_reads (user_id, notification_key)');
        $this->addSql('ALTER TABLE notification_reads ADD CONSTRAINT FK_EF48948A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE notification_reads DROP CONSTRAINT FK_EF48948A76ED395');
        $this->addSql('DROP TABLE notification_reads');
    }
}
