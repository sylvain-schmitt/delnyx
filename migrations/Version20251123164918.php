<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123164918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_logs (id SERIAL NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INT NOT NULL, recipient VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, error_message TEXT DEFAULT NULL, metadata JSON DEFAULT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id INT DEFAULT NULL, user_email VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_email_entity ON email_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_email_sent_at ON email_logs (sent_at)');
        $this->addSql('CREATE INDEX idx_email_status ON email_logs (status)');
        $this->addSql('COMMENT ON COLUMN email_logs.sent_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE email_logs');
    }
}
