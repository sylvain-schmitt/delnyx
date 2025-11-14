<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour supprimer les tables de jointure Many-to-Many quote_tariffs et amendment_tariffs
 * 
 * Ces tables étaient utilisées par EasyAdmin mais ne sont plus nécessaires
 * car le système utilise maintenant des lignes (QuoteLine/InvoiceLine) avec référence ManyToOne vers Tariff
 */
final class Version20251114162002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les tables quote_tariffs et amendment_tariffs (migration vers backend custom, suppression EasyAdmin)';
    }

    public function up(Schema $schema): void
    {
        // Supprimer les contraintes de clés étrangères
        $this->addSql('ALTER TABLE quote_tariffs DROP CONSTRAINT IF EXISTS FK_DAC10F2EDB805178');
        $this->addSql('ALTER TABLE quote_tariffs DROP CONSTRAINT IF EXISTS FK_DAC10F2E92348FD2');
        $this->addSql('ALTER TABLE amendment_tariffs DROP CONSTRAINT IF EXISTS FK_24495B1F4DAB577D');
        $this->addSql('ALTER TABLE amendment_tariffs DROP CONSTRAINT IF EXISTS FK_24495B1F92348FD2');

        // Supprimer les index
        $this->addSql('DROP INDEX IF EXISTS IDX_DAC10F2EDB805178');
        $this->addSql('DROP INDEX IF EXISTS IDX_DAC10F2E92348FD2');
        $this->addSql('DROP INDEX IF EXISTS IDX_24495B1F4DAB577D');
        $this->addSql('DROP INDEX IF EXISTS IDX_24495B1F92348FD2');

        // Supprimer les tables
        $this->addSql('DROP TABLE IF EXISTS quote_tariffs');
        $this->addSql('DROP TABLE IF EXISTS amendment_tariffs');
    }

    public function down(Schema $schema): void
    {
        // Recréer les tables (pour rollback)
        $this->addSql('CREATE TABLE quote_tariffs (quote_id INT NOT NULL, tariff_id INT NOT NULL, PRIMARY KEY(quote_id, tariff_id))');
        $this->addSql('CREATE INDEX IDX_DAC10F2EDB805178 ON quote_tariffs (quote_id)');
        $this->addSql('CREATE INDEX IDX_DAC10F2E92348FD2 ON quote_tariffs (tariff_id)');
        $this->addSql('CREATE TABLE amendment_tariffs (amendment_id INT NOT NULL, tariff_id INT NOT NULL, PRIMARY KEY(amendment_id, tariff_id))');
        $this->addSql('CREATE INDEX IDX_24495B1F4DAB577D ON amendment_tariffs (amendment_id)');
        $this->addSql('CREATE INDEX IDX_24495B1F92348FD2 ON amendment_tariffs (tariff_id)');

        // Recréer les contraintes
        $this->addSql('ALTER TABLE quote_tariffs ADD CONSTRAINT FK_DAC10F2EDB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE quote_tariffs ADD CONSTRAINT FK_DAC10F2E92348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendment_tariffs ADD CONSTRAINT FK_24495B1F4DAB577D FOREIGN KEY (amendment_id) REFERENCES amendments (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE amendment_tariffs ADD CONSTRAINT FK_24495B1F92348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
