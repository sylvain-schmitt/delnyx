<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250820100401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE project (id SERIAL NOT NULL, titre VARCHAR(200) NOT NULL, description TEXT NOT NULL, url VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN project.date_creation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN project.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE project_technology (project_id INT NOT NULL, technology_id INT NOT NULL, PRIMARY KEY(project_id, technology_id))');
        $this->addSql('CREATE INDEX IDX_ECC5297F166D1F9C ON project_technology (project_id)');
        $this->addSql('CREATE INDEX IDX_ECC5297F4235D463 ON project_technology (technology_id)');
        $this->addSql('CREATE TABLE project_image (id SERIAL NOT NULL, projet_id INT NOT NULL, fichier VARCHAR(255) NOT NULL, alt_text VARCHAR(255) DEFAULT NULL, ordre INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D6680DC1C18272 ON project_image (projet_id)');
        $this->addSql('COMMENT ON COLUMN project_image.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN project_image.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE technology (id SERIAL NOT NULL, nom VARCHAR(100) NOT NULL, couleur VARCHAR(7) NOT NULL, icone VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN technology.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN technology.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE project_technology ADD CONSTRAINT FK_ECC5297F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_technology ADD CONSTRAINT FK_ECC5297F4235D463 FOREIGN KEY (technology_id) REFERENCES technology (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project_image ADD CONSTRAINT FK_D6680DC1C18272 FOREIGN KEY (projet_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE project_technology DROP CONSTRAINT FK_ECC5297F166D1F9C');
        $this->addSql('ALTER TABLE project_technology DROP CONSTRAINT FK_ECC5297F4235D463');
        $this->addSql('ALTER TABLE project_image DROP CONSTRAINT FK_D6680DC1C18272');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_technology');
        $this->addSql('DROP TABLE project_image');
        $this->addSql('DROP TABLE technology');
    }
}
