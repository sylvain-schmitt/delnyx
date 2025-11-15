<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour créer la table audit_logs
 * 
 * Conformité légale : Archivage 10 ans obligatoire pour les documents contractuels
 */
final class Version20250101000000_CreateAuditLog extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table audit_logs pour la traçabilité des actions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE audit_logs (
                id SERIAL PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INTEGER NOT NULL,
                action VARCHAR(50) NOT NULL,
                old_value JSON,
                new_value JSON,
                metadata JSON,
                user_id INTEGER,
                user_email VARCHAR(255),
                document_hash VARCHAR(64),
                created_at TIMESTAMP NOT NULL
            )
        ');

        $this->addSql('CREATE INDEX idx_entity ON audit_logs (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_user ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX idx_created_at ON audit_logs (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
    }
}
