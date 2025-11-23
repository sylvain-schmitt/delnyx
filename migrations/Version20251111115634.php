<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251111115634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quotes ADD use_per_line_tva BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE quotes ALTER numero DROP NOT NULL');
        $this->addSql('ALTER TABLE quotes ALTER taux_tva SET DEFAULT \'0\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE quotes DROP use_per_line_tva');
        $this->addSql('ALTER TABLE quotes ALTER numero SET NOT NULL');
        $this->addSql('ALTER TABLE quotes ALTER taux_tva SET DEFAULT \'20\'');
    }
}
