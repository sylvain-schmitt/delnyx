<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ e_invoicing_mode sur les factures (b2b_einvoicing / b2c_ereporting)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices ADD COLUMN e_invoicing_mode VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices DROP COLUMN e_invoicing_mode');
    }
}
