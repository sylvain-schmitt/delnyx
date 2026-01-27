<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260126175753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE credit_notes ADD amendment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE credit_notes ADD CONSTRAINT FK_597428224DAB577D FOREIGN KEY (amendment_id) REFERENCES amendments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_597428224DAB577D ON credit_notes (amendment_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE credit_notes DROP CONSTRAINT FK_597428224DAB577D');
        $this->addSql('DROP INDEX UNIQ_597428224DAB577D');
        $this->addSql('ALTER TABLE credit_notes DROP amendment_id');
    }
}
