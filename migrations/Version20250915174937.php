<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915174937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avenants ADD devis_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE avenants ADD facture_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE avenants ADD CONSTRAINT FK_CB6C27A041DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE avenants ADD CONSTRAINT FK_CB6C27A07F2DEE08 FOREIGN KEY (facture_id) REFERENCES factures (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_CB6C27A041DEFADA ON avenants (devis_id)');
        $this->addSql('CREATE INDEX IDX_CB6C27A07F2DEE08 ON avenants (facture_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE avenants DROP CONSTRAINT FK_CB6C27A041DEFADA');
        $this->addSql('ALTER TABLE avenants DROP CONSTRAINT FK_CB6C27A07F2DEE08');
        $this->addSql('DROP INDEX IDX_CB6C27A041DEFADA');
        $this->addSql('DROP INDEX IDX_CB6C27A07F2DEE08');
        $this->addSql('ALTER TABLE avenants DROP devis_id');
        $this->addSql('ALTER TABLE avenants DROP facture_id');
    }
}
