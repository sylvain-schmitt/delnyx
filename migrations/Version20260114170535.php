<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114170535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE company_settings ADD forme_juridique VARCHAR(100) DEFAULT \'Auto-entrepreneur\' NOT NULL');
        $this->addSql('ALTER TABLE company_settings ADD code_ape VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD assurance_rcpro TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE company_settings ADD indemnite_forfaitaire_recouvrement NUMERIC(5, 2) DEFAULT \'40.00\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE company_settings DROP forme_juridique');
        $this->addSql('ALTER TABLE company_settings DROP code_ape');
        $this->addSql('ALTER TABLE company_settings DROP assurance_rcpro');
        $this->addSql('ALTER TABLE company_settings DROP indemnite_forfaitaire_recouvrement');
    }
}
