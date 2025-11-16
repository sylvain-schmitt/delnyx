<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251116013922 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE amendment_lines ADD source_line_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE amendment_lines ADD old_value NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE amendment_lines ADD new_value NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE amendment_lines ADD delta NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE amendment_lines ADD CONSTRAINT FK_BED39D4C4A4CD2A FOREIGN KEY (source_line_id) REFERENCES quote_lines (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_BED39D4C4A4CD2A ON amendment_lines (source_line_id)');
        $this->addSql('ALTER TABLE credit_note_lines ADD source_line_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_note_lines ADD old_value NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE credit_note_lines ADD new_value NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE credit_note_lines ADD delta NUMERIC(10, 2) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE credit_note_lines ADD CONSTRAINT FK_D96994FF4A4CD2A FOREIGN KEY (source_line_id) REFERENCES invoice_lines (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D96994FF4A4CD2A ON credit_note_lines (source_line_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE credit_note_lines DROP CONSTRAINT FK_D96994FF4A4CD2A');
        $this->addSql('DROP INDEX IDX_D96994FF4A4CD2A');
        $this->addSql('ALTER TABLE credit_note_lines DROP source_line_id');
        $this->addSql('ALTER TABLE credit_note_lines DROP old_value');
        $this->addSql('ALTER TABLE credit_note_lines DROP new_value');
        $this->addSql('ALTER TABLE credit_note_lines DROP delta');
        $this->addSql('ALTER TABLE amendment_lines DROP CONSTRAINT FK_BED39D4C4A4CD2A');
        $this->addSql('DROP INDEX IDX_BED39D4C4A4CD2A');
        $this->addSql('ALTER TABLE amendment_lines DROP source_line_id');
        $this->addSql('ALTER TABLE amendment_lines DROP old_value');
        $this->addSql('ALTER TABLE amendment_lines DROP new_value');
        $this->addSql('ALTER TABLE amendment_lines DROP delta');
    }
}
