<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Répare les factures créées par le webhook Stripe avec company_id = '1'.
 *
 * Le cloisonnement multi-tenant compare company_id à un UUID v5 dérivé de l'e-mail de
 * l'administrateur. Faute d'utilisateur connecté, le webhook posait la chaîne '1' quand
 * le client n'avait aucune facture antérieure — une valeur qui ne correspond à aucun
 * UUID. Ces factures devenaient inaccessibles à leur propre propriétaire, avec le message
 * « Vous n'avez pas accès à cette facture » dès qu'il tentait d'émettre un avoir.
 *
 * Migration de DONNÉES uniquement, aucune structure touchée. Idempotente : sans facture
 * en '1', elle ne modifie rien. La cause a été corrigée dans StripeService, cette
 * migration ne traite que l'existant.
 */
final class Version20260808060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réattribue les factures webhook orphelines (company_id = 1) à la société réelle';
    }

    public function up(Schema $schema): void
    {
        // La société réelle est celle de la facture la plus récente portant un vrai UUID.
        // En installation mono-société — le cas ici — cette valeur est sans ambiguïté.
        // Le WHERE final protège une base qui n'aurait aucune facture de référence :
        // la sous-requête renverrait NULL et écraserait des données.
        $this->addSql(<<<'SQL'
            UPDATE invoices
            SET company_id = (
                SELECT company_id FROM invoices
                WHERE company_id IS NOT NULL AND company_id <> '1'
                ORDER BY id DESC LIMIT 1
            )
            WHERE company_id = '1'
              AND EXISTS (
                SELECT 1 FROM invoices
                WHERE company_id IS NOT NULL AND company_id <> '1'
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Aucun retour en arrière : on ne saurait pas distinguer les factures
        // réattribuées de celles qui portaient déjà ce company_id. Le rétablissement
        // du '1' serait de toute façon un retour à l'état cassé.
        $this->throwIrreversibleMigrationException(
            'Réattribution de company_id irréversible : le retour à "1" restaurerait le bug.'
        );
    }
}
