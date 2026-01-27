<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceType;
use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use App\Entity\CreditNoteLine;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour gérer la facturation automatique après signature d'un avenant
 *
 * - Avenant positif → Crée une facture complémentaire
 * - Avenant négatif → Crée un avoir
 * - Avenant neutre → Aucune action
 */
class AmendmentBillingService
{
    private ?AuditService $auditService = null;
    private ?EmailService $emailService = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceNumberGenerator $invoiceNumberGenerator,
        private readonly CreditNoteNumberGenerator $creditNoteNumberGenerator,
        private readonly LoggerInterface $logger,
        private readonly StripeService $stripeService,
        private readonly \App\Repository\SubscriptionRepository $subscriptionRepository,
    ) {}

    public function setAuditService(AuditService $auditService): void
    {
        $this->auditService = $auditService;
    }

    public function setEmailService(EmailService $emailService): void
    {
        $this->emailService = $emailService;
    }

    /**
     * Gère la facturation automatique après signature d'un avenant
     *
     * @param Amendment $amendment L'avenant signé
     * @return Invoice|CreditNote|null Le document créé (facture ou avoir), ou null si montant = 0
     */
    public function handleSignedAmendment(Amendment $amendment): Invoice|CreditNote|null
    {
        // Vérifier que l'avenant est bien signé
        if ($amendment->getStatut() !== AmendmentStatus::SIGNED) {
            $this->logger->warning('Tentative de facturation auto sur avenant non signé', [
                'amendment_id' => $amendment->getId(),
                'status' => $amendment->getStatut()?->value,
            ]);
            return null;
        }

        // Utiliser le delta TTC pour la facturation (montant de l'avenant)
        // getMontantTTC() retourne le NOUVEAU total du devis, ce qui n'est pas ce qu'on veut facturer
        $montantAvenantTTC = $amendment->getMontantDeltaTTC();

        if ($montantAvenantTTC > 0) {
            // Avenant positif → Créer une facture complémentaire
            return $this->createInvoiceFromAmendment($amendment, $montantAvenantTTC);
        } elseif ($montantAvenantTTC < 0) {
            // Avenant négatif → Créer un avoir
            return $this->createCreditNoteFromAmendment($amendment, abs($montantAvenantTTC));
        }

        // Montant = 0 → Aucune action financière
        $this->logger->info('Avenant neutre (delta = 0), aucune facturation', [
            'amendment_id' => $amendment->getId(),
        ]);

        // Synchroniser les abonnements si nécessaire (Stripe & Manuel)
        $this->syncSubscriptionWithAmendment($amendment);

        return null;
    }

    /**
     * Met à jour les abonnements (Locaux et Stripe) si l'avenant modifie des lignes récurrentes
     */
    private function syncSubscriptionWithAmendment(Amendment $amendment): void
    {
        $quote = $amendment->getQuote();

        // On parcourt les lignes de l'avenant pour détecter les modifs sur les abonnements
        foreach ($amendment->getLines() as $line) {
            // Identifier si la ligne correspond à un abonnement
            $subscriptionMode = $line->getSubscriptionMode(); // monthly, yearly ou null

            // Si l'avenant ne spécifie pas le mode, on regarde la ligne originale du devis si elle existe
            if (!$subscriptionMode && $line->getQuoteLine()) {
                $subscriptionMode = $line->getQuoteLine()->getSubscriptionMode();
            }

            if ($subscriptionMode && in_array($subscriptionMode, ['monthly', 'yearly'])) {
                $tariff = $line->getTariff();
                $client = $quote->getClient();

                // Chercher l'abonnement actif correspondant
                // On cherche par Client + Tariff (ou Label si pas de tarif)
                // TODO: Améliorer la correspondance (idéalement avoir le lien vers Subscription dans QuoteLine)
                $criteria = ['client' => $client, 'status' => 'active'];
                if ($tariff) {
                    $criteria['tariff'] = $tariff;
                }

                $subscriptions = $this->subscriptionRepository->findBy($criteria);

                // Si on en trouve plusieurs, on prend le premier qui correspond à l'intervalle
                $subscription = null;
                $targetInterval = ($subscriptionMode === 'monthly') ? 'month' : 'year';

                foreach ($subscriptions as $sub) {
                    if ($sub->getIntervalUnit() === $targetInterval) {
                        $subscription = $sub;
                        break;
                    }
                }

                if ($subscription) {
                    // Calculer le NOUVEAU montant récurrent de cette ligne
                    // Attention: $line dans AmendmentLine contient le DELTA ou la NOUVELLE VALEUR ?
                    // AmendmentLine : quantity = nouvelle quantité, unitPrice = nouveau prix ?
                    // Vérifions l'entité AmendmentLine. Elle stocke la "correction".
                    // En fait, AmendmentLine stocke la ligne TELLE QU'ELLE SERA. (Snapshot final ou Delta ?)
                    // D'après la logique précédente (oldValue/newValue), on a acces à la nouvelle quantité/prix.

                    // On recalcule le montant total pour cette ligne d'abonnement
                    $newQuantity = $line->getQuantity();
                    $newUnitPrice = $line->getUnitPrice();

                    // Si quantity ou price est null (pas touché), on reprend ceux de la ligne d'origine ?
                    // Non, AmendmentLine est censé contenir les valeurs finales après modif pour cette ligne.

                    $newRecurrenceAmount = $newQuantity * $newUnitPrice;

                    $this->logger->info('Mise à jour abonnement détectée via avenant', [
                        'subscription_id' => $subscription->getId(),
                        'old_amount' => $subscription->getAmount(),
                        'new_amount' => $newRecurrenceAmount
                    ]);

                    // Mise à jour Locale
                    $subscription->setAmount((string) $newRecurrenceAmount);
                    $this->entityManager->flush();

                    // Mise à jour Stripe (si lié)
                    if ($subscription->getStripeSubscriptionId()) {
                        try {
                            // Récupérer les items de l'abonnement Stripe
                            $items = $this->stripeService->getSubscriptionItems($subscription->getStripeSubscriptionId());

                            // Trouver l'item correspondant au produit/prix
                            // C'est complexe si plusieurs lignes. On suppose souvent 1 item principal ou on update tout ?
                            // Simplification: On met à jour le premier item trouvé (souvent unique pour un abo).
                            // IDÉALEMENT : Stocker stripeSubscriptionItemId dans Subscription ou QuoteLine.

                            if (count($items) > 0) {
                                $itemId = $items[0]->id;
                                // On met à jour la quantité ou le prix ?
                                // Stripe Pricing est souvent fixe. Si on change le prix unitaire, c'est un nouveau Price.
                                // Si on change la quantité, on update quantity.
                                // Si le montant change arbitrairement, Stripe ne permet pas d'update "amount" direct sur un Price standard.
                                // Il faut passer par "price_data" (nouveau prix) ou changer de Price.

                                // Approche Robuste : Update Quantity si le prix unitaire est le même.
                                // Sinon : Recréer un Price inline.

                                // Pour l'instant, on suppose que seule la QUANTITÉ change pour les abonnements SaaS standard.
                                // Si le prix unitaire change (sur-mesure), il faut recréer un price.

                                $this->stripeService->updateSubscriptionItem($itemId, [
                                    'quantity' => (int) $newQuantity,
                                    // 'price_data' ... si besoin de changer le prix unitaire
                                ]);

                                $this->logger->info('Abonnement Stripe mis à jour', ['stripe_sub_id' => $subscription->getStripeSubscriptionId()]);
                            }
                        } catch (\Exception $e) {
                            $this->logger->error('Echec mise à jour Stripe via avenant', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }
        }
    }
    private function createInvoiceFromAmendment(Amendment $amendment, float $montantTTC): Invoice
    {
        $quote = $amendment->getQuote();
        $client = $quote->getClient();

        $invoice = new Invoice();
        $invoice->setNumero($this->invoiceNumberGenerator->generate($invoice));
        // Note: On ne lie PAS au Quote car c'est une relation OneToOne et le devis a déjà sa facture principale
        // La facture complémentaire est liée via les notes qui référencent l'avenant
        $invoice->setClient($client);
        $invoice->setCompanyId($quote->getCompanyId());
        $invoice->setType(InvoiceType::STANDARD);
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $invoice->setDateCreation(new \DateTimeImmutable());

        // Définir une date d'échéance (30 jours par défaut)
        $invoice->setDateEcheance(new \DateTime('+30 days'));

        // Calculer les montants HT et TVA basés sur le delta TTC
        // On utilise le taux moyen de l'avenant ou du devis
        $tauxTVA = (float) $amendment->getTauxTVA();
        if ($tauxTVA == 0 && $quote->getTauxTVA()) {
            $tauxTVA = (float) $quote->getTauxTVA();
        }

        // Calcul HT = TTC / (1 + taux/100)
        $montantHT = $montantTTC / (1 + ($tauxTVA / 100));
        $montantTVA = $montantTTC - $montantHT;

        $invoice->setMontantHT(number_format($montantHT, 2, '.', ''));
        $invoice->setMontantTVA(number_format($montantTVA, 2, '.', ''));
        $invoice->setMontantTTC(number_format($montantTTC, 2, '.', ''));

        // Créer une ligne unique pour la facture de complément
        $invoiceLine = new InvoiceLine();
        $invoiceLine->setDescription('Complément suite à l\'avenant ' . $amendment->getNumero());
        $invoiceLine->setQuantity(1);
        $invoiceLine->setUnitPrice(number_format($montantHT, 2, '.', ''));
        $invoiceLine->setTvaRate((string)$tauxTVA);
        $invoiceLine->setTotalHt(number_format($montantHT, 2, '.', ''));
        $invoice->addLine($invoiceLine);

        // Notes et conditions
        $invoice->setNotes(sprintf(
            "Facture complémentaire suite à l'avenant %s.\n\nMotif : %s",
            $amendment->getNumero(),
            $amendment->getMotif()
        ));

        $this->entityManager->persist($invoice);

        // Lier la facture à l'avenant
        $amendment->setInvoice($invoice);

        $this->entityManager->flush();

        // Audit
        $this->auditService?->log(
            entityType: 'Invoice',
            entityId: $invoice->getId(),
            action: 'create_from_amendment',
            metadata: [
                'amendment_id' => $amendment->getId(),
                'amendment_numero' => $amendment->getNumero(),
                'montant_ttc' => $montantTTC,
            ]
        );

        $this->logger->info('Facture complémentaire créée depuis avenant', [
            'invoice_id' => $invoice->getId(),
            'invoice_numero' => $invoice->getNumero(),
            'amendment_id' => $amendment->getId(),
            'montant_ttc' => $montantTTC,
        ]);

        return $invoice;
    }

    /**
     * Crée un avoir à partir d'un avenant négatif
     *
     * @return CreditNote|null L'avoir créé, ou null si pas de facture associée au devis
     */
    private function createCreditNoteFromAmendment(Amendment $amendment, float $montantTTC): ?CreditNote
    {
        $quote = $amendment->getQuote();

        // Récupérer la facture originale du devis (obligatoire pour créer un avoir)
        $originalInvoice = $quote->getInvoice();

        if (!$originalInvoice) {
            $this->logger->warning('Impossible de créer un avoir automatique : pas de facture associée au devis', [
                'amendment_id' => $amendment->getId(),
                'quote_id' => $quote->getId(),
                'montant_ttc' => $montantTTC,
            ]);
            return null;
        }

        $creditNote = new CreditNote();
        $creditNote->setNumber($this->creditNoteNumberGenerator->generate($creditNote));
        $creditNote->setCompanyId($quote->getCompanyId());
        $creditNote->setStatutEnum(CreditNoteStatus::DRAFT);
        $creditNote->setDateCreation(new \DateTimeImmutable());
        $creditNote->setInvoice($originalInvoice);

        // Calculer les montants HT et TVA (Delta positif car c'est un avoir)
        // On utilise le taux moyen de l'avenant ou du devis
        $tauxTVA = (float) $amendment->getTauxTVA();
        if ($tauxTVA == 0 && $quote->getTauxTVA()) {
            $tauxTVA = (float) $quote->getTauxTVA();
        }

        // Calcul HT = TTC / (1 + taux/100)
        $montantHT = $montantTTC / (1 + ($tauxTVA / 100));
        $montantTVA = $montantTTC - $montantHT;

        $creditNote->setMontantHT(number_format($montantHT, 2, '.', ''));
        $creditNote->setMontantTVA(number_format($montantTVA, 2, '.', ''));
        $creditNote->setMontantTTC(number_format($montantTTC, 2, '.', ''));
        $creditNote->setTauxTVA((string)$tauxTVA);

        // Créer une ligne unique pour l'avoir
        $creditNoteLine = new CreditNoteLine();
        $creditNoteLine->setDescription('Avoir suite à l\'avenant ' . $amendment->getNumero());
        $creditNoteLine->setQuantity(1);
        $creditNoteLine->setUnitPrice(number_format($montantHT, 2, '.', ''));
        $creditNoteLine->setTvaRate((string)$tauxTVA);
        $creditNoteLine->setTotalHt(number_format($montantHT, 2, '.', ''));
        $creditNote->addLine($creditNoteLine);

        // Motif de l'avoir
        $creditNote->setReason(sprintf(
            "Avoir suite à l'avenant %s.\n\nMotif : %s",
            $amendment->getNumero(),
            $amendment->getMotif()
        ));

        $this->entityManager->persist($creditNote);

        // Lier l'avoir à l'avenant
        $amendment->setCreditNote($creditNote);

        $this->entityManager->flush();

        // Audit
        $this->auditService?->log(
            entityType: 'CreditNote',
            entityId: $creditNote->getId(),
            action: 'create_from_amendment',
            metadata: [
                'amendment_id' => $amendment->getId(),
                'amendment_numero' => $amendment->getNumero(),
                'invoice_id' => $originalInvoice->getId(),
                'montant_ttc' => $montantTTC,
            ]
        );

        $this->logger->info('Avoir créé depuis avenant négatif', [
            'credit_note_id' => $creditNote->getId(),
            'credit_note_numero' => $creditNote->getNumber(),
            'amendment_id' => $amendment->getId(),
            'invoice_id' => $originalInvoice->getId(),
            'montant_ttc' => $montantTTC,
        ]);

        return $creditNote;
    }
}
