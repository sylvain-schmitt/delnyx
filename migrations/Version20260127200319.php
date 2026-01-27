<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260127200319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deposit (id SERIAL NOT NULL, quote_id INT NOT NULL, invoice_id INT DEFAULT NULL, amount INT NOT NULL, percentage NUMERIC(5, 2) DEFAULT NULL, status VARCHAR(20) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, deducted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, notes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_deposit_quote ON deposit (quote_id)');
        $this->addSql('CREATE INDEX idx_deposit_status ON deposit (status)');
        $this->addSql('CREATE INDEX idx_deposit_invoice ON deposit (invoice_id)');
        $this->addSql('COMMENT ON COLUMN deposit.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN deposit.paid_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN deposit.deducted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE subscriptions (id SERIAL NOT NULL, client_id INT NOT NULL, tariff_id INT DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, status VARCHAR(50) NOT NULL, interval_unit VARCHAR(20) NOT NULL, amount NUMERIC(10, 2) NOT NULL, current_period_start TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, current_period_end TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, custom_label VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4778A0119EB6921 ON subscriptions (client_id)');
        $this->addSql('CREATE INDEX IDX_4778A0192348FD2 ON subscriptions (tariff_id)');
        $this->addSql('ALTER TABLE deposit ADD CONSTRAINT FK_95DB9D39DB805178 FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE deposit ADD CONSTRAINT FK_95DB9D392989F1FD FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A0119EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT FK_4778A0192348FD2 FOREIGN KEY (tariff_id) REFERENCES tariffs (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE clients ADD stripe_customer_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD source_deposit_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD subscription_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD stripe_invoice_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD type VARCHAR(20) DEFAULT \'standard\' NOT NULL');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F95E4A19595 FOREIGN KEY (source_deposit_id) REFERENCES deposit (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT FK_6A2F2F959A1887DC FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2F2F9552875775 ON invoices (stripe_invoice_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A2F2F95E4A19595 ON invoices (source_deposit_id)');
        $this->addSql('CREATE INDEX IDX_6A2F2F959A1887DC ON invoices (subscription_id)');
        $this->addSql('ALTER TABLE tariffs ADD has_recurrence BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE tariffs ADD prix_mensuel NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE tariffs ADD prix_annuel NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE tariffs ADD stripe_product_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tariffs ADD stripe_price_id_monthly VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE tariffs ADD stripe_price_id_yearly VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F95E4A19595');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT FK_6A2F2F959A1887DC');
        $this->addSql('ALTER TABLE deposit DROP CONSTRAINT FK_95DB9D39DB805178');
        $this->addSql('ALTER TABLE deposit DROP CONSTRAINT FK_95DB9D392989F1FD');
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT FK_4778A0119EB6921');
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT FK_4778A0192348FD2');
        $this->addSql('DROP TABLE deposit');
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP INDEX UNIQ_6A2F2F9552875775');
        $this->addSql('DROP INDEX UNIQ_6A2F2F95E4A19595');
        $this->addSql('DROP INDEX IDX_6A2F2F959A1887DC');
        $this->addSql('ALTER TABLE invoices DROP source_deposit_id');
        $this->addSql('ALTER TABLE invoices DROP subscription_id');
        $this->addSql('ALTER TABLE invoices DROP stripe_invoice_id');
        $this->addSql('ALTER TABLE invoices DROP type');
        $this->addSql('ALTER TABLE tariffs DROP has_recurrence');
        $this->addSql('ALTER TABLE tariffs DROP prix_mensuel');
        $this->addSql('ALTER TABLE tariffs DROP prix_annuel');
        $this->addSql('ALTER TABLE tariffs DROP stripe_product_id');
        $this->addSql('ALTER TABLE tariffs DROP stripe_price_id_monthly');
        $this->addSql('ALTER TABLE tariffs DROP stripe_price_id_yearly');
        $this->addSql('ALTER TABLE clients DROP stripe_customer_id');
    }
}
