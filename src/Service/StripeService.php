<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Client;
use App\Entity\Subscription;
use App\Entity\Tariff;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\InvoiceService;
use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\InvoiceLine;
use App\Service\EmailService;
use App\Service\MagicLinkService; // AJOUTÉ
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;

class StripeService
{
    private ?StripeClient $stripe = null;
    private ?string $fallbackSecretKey;

    public function __construct(
        private readonly \App\Repository\CompanySettingsRepository $settingsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly \App\Repository\ClientRepository $clientRepository,
        private readonly InvoiceService $invoiceService,
        private readonly EmailService $emailService,
        private readonly MagicLinkService $magicLinkService,
        private readonly LoggerInterface $logger,
        ?string $stripeSecretKey = null
    ) {
        // Stocke la clé .env comme fallback
        $this->fallbackSecretKey = $stripeSecretKey;
    }

    /**
     * Récupère le client Stripe avec la clé depuis CompanySettings ou .env en fallback
     */
    private function getStripeClient(): ?StripeClient
    {
        if ($this->stripe !== null) {
            return $this->stripe;
        }

        // Priorité 1 : Clé depuis CompanySettings
        $settings = $this->settingsRepository->findFirst();
        if ($settings && $settings->hasValidStripeConfig()) {
            $this->stripe = new StripeClient($settings->getStripeSecretKey());
            return $this->stripe;
        }

        // Priorité 2 : Fallback sur .env
        if (!empty($this->fallbackSecretKey)) {
            $this->stripe = new StripeClient($this->fallbackSecretKey);
            return $this->stripe;
        }

        return null;
    }

    /**
     * Vérifie si Stripe est configuré
     */
    public function isConfigured(): bool
    {
        return $this->getStripeClient() !== null;
    }

    /**
     * Récupère la clé publique Stripe
     */
    public function getPublishableKey(): ?string
    {
        $settings = $this->settingsRepository->findFirst();
        if ($settings && $settings->hasValidStripeConfig()) {
            return $settings->getStripePublishableKey();
        }

        // Fallback sur env (pas idéal mais rétrocompatible)
        return $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? null;
    }

    /**
     * Récupère le secret webhook Stripe
     */
    public function getWebhookSecret(): ?string
    {
        $settings = $this->settingsRepository->findFirst();
        if ($settings && !empty($settings->getStripeWebhookSecret())) {
            return $settings->getStripeWebhookSecret();
        }

        // Fallback sur env
        return $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;
    }

    /**
     * Récupère la clé secrète Stripe pour les appels API directs
     * Note: Préférez utiliser getStripeClient() quand possible
     */
    public function getSecretKey(): ?string
    {
        $settings = $this->settingsRepository->findFirst();
        if ($settings && $settings->hasValidStripeConfig()) {
            return $settings->getStripeSecretKey();
        }

        // Fallback sur env
        return $this->fallbackSecretKey ?: ($_ENV['STRIPE_SECRET_KEY'] ?? null);
    }


    /**
     * Crée ou récupère un client Stripe pour un client Delnyx
     */
    public function ensureStripeCustomer(Client $client): string
    {
        if ($client->getStripeCustomerId()) {
            return $client->getStripeCustomerId();
        }

        try {
            // Chercher par email d'abord pour éviter doublons
            $existing = $this->getStripeClient()->customers->search([
                'query' => "email:'" . $client->getEmail() . "'",
            ]);

            if (count($existing->data) > 0) {
                $customerId = $existing->data[0]->id;
            } else {
                $customer = $this->getStripeClient()->customers->create([
                    'email' => $client->getEmail(),
                    'name' => $client->getNomComplet(),
                    'phone' => $client->getTelephone(),
                    'metadata' => [
                        'client_id' => $client->getId(),
                    ]
                ]);
                $customerId = $customer->id;
            }

            $client->setStripeCustomerId($customerId);
            $this->entityManager->flush();

            return $customerId;
        } catch (\Exception $e) {
            $this->logger->error('Erreur création client Stripe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crée un abonnement pour un client
     *
     * @param Client $client
     * @param array $items Liste d'items formatés (soit ['price' => 'id'], soit ['price_data' => ...])
     * @return \Stripe\Subscription
     */
    public function createSubscription(Client $client, array $items): \Stripe\Subscription
    {
        $customerId = $this->ensureStripeCustomer($client);

        try {
            $subscription = $this->getStripeClient()->subscriptions->create([
                'customer' => $customerId,
                'items' => $items,
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'collection_method' => 'charge_automatically',
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'source' => 'delnyx_admin'
                ]
            ]);

            return $subscription;
        } catch (\Exception $e) {
            $this->logger->error('Erreur création abonnement Stripe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Annule un abonnement
     */
    public function cancelSubscription(Subscription $subscription): void
    {
        if (!$subscription->getStripeSubscriptionId()) {
            return;
        }

        try {
            $stripeSub = $this->getStripeClient()->subscriptions->cancel(
                $subscription->getStripeSubscriptionId(),
                []
            );

            if ($stripeSub->status === 'canceled') {
                $subscription->setStatus('canceled');
                $this->entityManager->flush();

                // Notification Admin
                try {
                    $this->emailService->sendSubscriptionNotificationAdmin('résiliation', $subscription);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur notification admin annulation sub: ' . $e->getMessage());
                }
            } else {
                $this->logger->warning('Annulation incomplète Stripe le statut retourné est : ' . $stripeSub->status);
                // On peut décider de mettre 'canceled' ou un autre statut intermédiaire
                // Pour l'instant on garde le comportement : seulement si confirmé annulé
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur annulation abonnement Stripe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Synchronise le statut d'un abonnement local avec Stripe
     */
    public function syncSubscriptionStatus(Subscription $subscription): void
    {
        if (!$subscription->getStripeSubscriptionId()) {
            return;
        }

        try {
            $stripeSub = $this->getStripeClient()->subscriptions->retrieve($subscription->getStripeSubscriptionId(), ['expand' => ['items.data.price']]);
            $subscription->setStatus($stripeSub->status);

            // Synchroniser le montant et l'unité de l'abonnement
            if (!empty($stripeSub->items->data)) {
                $item = $stripeSub->items->data[0];
                $price = $item->price;
                if ($price && isset($price->unit_amount)) {
                    $subscription->setAmount((string) ($price->unit_amount / 100));
                }



                if ($price && isset($price->recurring->interval)) {
                    $subscription->setIntervalUnit($price->recurring->interval);
                }
            }

            if (!empty($stripeSub->current_period_start)) {
                $subscription->setCurrentPeriodStart((new \DateTime())->setTimestamp($stripeSub->current_period_start));
            }

            if (!empty($stripeSub->current_period_end)) {
                $subscription->setCurrentPeriodEnd((new \DateTime())->setTimestamp($stripeSub->current_period_end));
            }

            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Erreur sync abonnement: ' . $e->getMessage());
        }
    }

    /**
     * Récupère un PaymentIntent Stripe
     */
    public function retrievePaymentIntent(string $paymentIntentId): ?\Stripe\PaymentIntent
    {
        try {
            return $this->getStripeClient()->paymentIntents->retrieve($paymentIntentId);
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération PaymentIntent Stripe: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crée un remboursement Stripe
     */
    public function createRefund(string $paymentIntentId, float $amount, array $metadata = []): \Stripe\Refund
    {
        try {
            return $this->getStripeClient()->refunds->create([
                'payment_intent' => $paymentIntentId,
                'amount' => (int) round($amount * 100),
                'metadata' => $metadata
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur remboursement Stripe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupère une session Stripe Checkout
     */
    public function retrieveSession(string $sessionId): ?\Stripe\Checkout\Session
    {
        try {
            return $this->getStripeClient()->checkout->sessions->retrieve($sessionId, ['expand' => ['customer']]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération session Stripe: ' . $e->getMessage());
            return null;
        }
    }
    /**
     * Crée ou met à jour une souscription locale à partir d'une session Stripe
     */
    public function createOrUpdateSubscriptionFromSession(\Stripe\Checkout\Session $session): void
    {
        $stripeSubscriptionId = $session->subscription;
        $stripeCustomerId = $session->customer instanceof \Stripe\Customer ? $session->customer->id : $session->customer;

        // Récupérer les métadonnées depuis la session
        $clientId = $session->metadata->client_id ?? null;
        $invoiceId = $session->metadata->invoice_id ?? null;

        // Si métadonnées manquantes dans la session, tenter de les récupérer de l'abonnement lui-même
        if ((!$clientId || !$invoiceId) && $stripeSubscriptionId && is_string($stripeSubscriptionId)) {
            try {
                $stripeSub = $this->getStripeClient()->subscriptions->retrieve($stripeSubscriptionId);
                $clientId = $clientId ?? ($stripeSub->metadata->client_id ?? null);
                $invoiceId = $invoiceId ?? ($stripeSub->metadata->invoice_id ?? null);
                error_log(sprintf('DEBUG: Metadata from Subscription object. Client: %s, Invoice: %s', $clientId ?? 'NULL', $invoiceId ?? 'NULL'));
            } catch (\Exception $e) {
                error_log('DEBUG: Could not retrieve subscription for metadata: ' . $e->getMessage());
            }
        }

        error_log(sprintf(
            'DEBUG: StripeService::createOrUpdateSubscriptionFromSession. Session: %s, Sub: %s, Client: %s, Invoice: %s',
            $session->id,
            $stripeSubscriptionId ?? 'NULL',
            $clientId ?? 'NULL',
            $invoiceId ?? 'NULL'
        ));

        if (!$stripeSubscriptionId) {
            error_log('DEBUG: No stripeSubscriptionId found in session metadata or object.');
            return;
        }

        // 1. Sync Customer ID
        $client = null;
        if ($clientId) {
            $client = $this->clientRepository->find($clientId);
            if ($client && $stripeCustomerId && !$client->getStripeCustomerId()) {
                $client->setStripeCustomerId((string) $stripeCustomerId);
                $this->entityManager->flush();
                $this->logger->info('Stripe Customer ID synchronisé via session', ['client_id' => $clientId]);
            }
        }

        if (!$client && $stripeCustomerId) {
            // Tentative de retrouver le client par Stripe ID
            $client = $this->clientRepository->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
        }

        if (!$client) {
            $this->logger->error('Client introuvable pour la session ' . $session->id);
            return;
        }

        // 2. Vérifier si l'abonnement existe déjà
        $subscription = $this->subscriptionRepository->findOneBy(['stripeSubscriptionId' => $stripeSubscriptionId]);

        if (!$subscription) {
            error_log('DEBUG: Subscription not found locally for Stripe ID ' . $stripeSubscriptionId . '. Creating one.');
        } else {
            error_log('DEBUG: Subscription found locally with ID ' . $subscription->getId());
        }

        if (!$subscription && $invoiceId) {
            // Upgrade d'abonnement manuel ?
            $invoice = $this->entityManager->getRepository(Invoice::class)->find($invoiceId);
            if ($invoice && $invoice->getSubscription()) {
                $subToCheck = $invoice->getSubscription();
                if (!$subToCheck->getStripeSubscriptionId()) {
                    $subscription = $subToCheck;
                    $subscription->setStripeSubscriptionId($stripeSubscriptionId);
                    $this->logger->info('Abonnement manuel upgradé vers Stripe', ['sub_id' => $subscription->getId()]);
                    error_log('DEBUG: Manual subscription upgraded to Stripe ID.');
                }
            }
        }

        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setClient($client);
            $subscription->setStripeSubscriptionId($stripeSubscriptionId);
            $subscription->setStatus('active');
            $subscription->setIntervalUnit('month'); // Valeur par défaut, sera mise à jour par sync
            $subscription->setAmount('0.00');
            $subscription->setCurrentPeriodStart(new \DateTime());
            $subscription->setCurrentPeriodEnd((new \DateTime())->modify('+1 month'));
            $this->entityManager->persist($subscription);

            // Lier l'abonnement à la facture si on a l'ID
            if ($invoiceId) {
                $invoice = $this->entityManager->getRepository(Invoice::class)->find($invoiceId);
                if ($invoice) {
                    $invoice->setSubscription($subscription);
                    $this->logger->info('Abonnement lié à la facture', ['invoice_id' => $invoiceId]);
                }
            }

            $this->entityManager->flush();
            $this->logger->info('Nouvel abonnement créé localement', ['stripe_id' => $stripeSubscriptionId]);
            error_log('DEBUG: New subscription persisted local ID ' . $subscription->getId());

            // Notification Admin
            try {
                $this->emailService->sendSubscriptionNotificationAdmin('création', $subscription);
            } catch (\Exception $e) {
                $this->logger->error('Erreur notification admin création sub: ' . $e->getMessage());
            }
        }

        // 3. Synchroniser les détails
        $this->syncSubscriptionStatus($subscription);
    }
    /**
     * Récupère les items d'un abonnement Stripe
     */
    public function getSubscriptionItems(string $subscriptionId): array
    {
        try {
            $items = $this->getStripeClient()->subscriptionItems->all([
                'subscription' => $subscriptionId,
            ]);
            return $items->data;
        } catch (\Exception $e) {
            $this->logger->error('Erreur récupération items abonnement Stripe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Met à jour le prix ou la quantité d'un item d'abonnement
     */
    public function updateSubscriptionItem(string $itemId, array $params): void
    {
        try {
            $this->getStripeClient()->subscriptionItems->update($itemId, $params);
        } catch (\Exception $e) {
            $this->logger->error('Erreur mise à jour item abonnement Stripe: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Gère le webhook invoice.payment_succeeded pour le renouvellement d'abonnement
     */
    /**
     * @param \Stripe\Invoice|object $stripeInvoice
     */
    public function handleInvoicePaymentSucceeded(object $stripeInvoice): void
    {
        $this->logger->info("DEBUG: handleInvoicePaymentSucceeded ENTERED for Invoice ID: " . ($stripeInvoice->id ?? 'unknown'));
        $subscriptionId = $stripeInvoice->subscription ?? null;

        // Fallback 1: Check in lines
        if (!$subscriptionId && isset($stripeInvoice->lines->data[0]->subscription)) {
            $subscriptionId = $stripeInvoice->lines->data[0]->subscription;
        }

        // Fallback 2: Check in parent (new structure?)
        if (!$subscriptionId && isset($stripeInvoice->parent->subscription_details->subscription)) {
            $subscriptionId = $stripeInvoice->parent->subscription_details->subscription;
        }

        // Fallback 3: Check in lines parent
        if (!$subscriptionId && isset($stripeInvoice->lines->data[0]->parent->subscription_item_details->subscription)) {
            $subscriptionId = $stripeInvoice->lines->data[0]->parent->subscription_item_details->subscription;
        }


        $billingReason = $stripeInvoice->billing_reason ?? 'unknown';
        $amount = $stripeInvoice->amount_paid ?? 0;

        $this->logger->info("Webhook invoice.payment_succeeded. ID: {$stripeInvoice->id}, Sub: " . ($subscriptionId ?? 'NULL') . ", Reason: $billingReason, Amount: $amount");
        $this->logger->info("Invoice Dump: " . json_encode($stripeInvoice->toArray()));

        // On ne traite que les factures liées à un abonnement
        if (!$subscriptionId) {
            $this->logger->info("Ignored invoice {$stripeInvoice->id}: No subscription ID found (checked top-level and lines).");
            return;
        }

        // On vérifie le billing_reason
        $allowedReasons = ['subscription_cycle', 'subscription_create', 'subscription_update', 'upcoming_invoice_now', 'manual'];
        if (!in_array($billingReason, $allowedReasons) && strpos($billingReason, 'subscription') === false) {
            $this->logger->info("Ignored invoice {$stripeInvoice->id}: Reason '$billingReason' not supported.");
            return;
        }

        $subscription = $this->subscriptionRepository->findOneBy(['stripeSubscriptionId' => $subscriptionId]);
        $invoiceId = $stripeInvoice->metadata->invoice_id ?? null;
        $clientId = $stripeInvoice->metadata->client_id ?? null;

        if (!$subscription) {
            if ($clientId) {
                $client = $this->entityManager->getRepository(Client::class)->find($clientId);
                if ($client) {
                    $subscription = new Subscription();
                    $subscription->setClient($client);
                    $subscription->setStripeSubscriptionId($subscriptionId);
                    $subscription->setStatus('active');
                    $subscription->setAmount('0.00'); // Default
                    $subscription->setIntervalUnit('month'); // Default
                    $subscription->setCurrentPeriodStart(new \DateTime());
                    $subscription->setCurrentPeriodEnd((new \DateTime())->modify('+1 month'));
                    $this->entityManager->persist($subscription);

                    // On force une synchro pour avoir les vraies dates et montants
                    $this->syncSubscriptionStatus($subscription);
                    $this->entityManager->flush();
                }
            }
        }

        if (!$subscription) {
            $this->logger->warning('Subscription not found for webhook invoice', ['stripe_sub_id' => $subscriptionId]);
            return;
        }

        if ($invoiceId && $subscription) {
            $localInvoice = $this->entityManager->getRepository(Invoice::class)->find($invoiceId);
            if ($localInvoice && !$localInvoice->getSubscription()) {
                $localInvoice->setSubscription($subscription);
            }
        }

        // Si c'est subscription_create, on a peut-être déjà créé la facture via le checkout flow.
        // On vérifie si une facture existe déjà pour cette période ?
        // Simplification : On traite 'subscription_cycle' prioritairement pour le renouvellement.
        if ($billingReason === 'subscription_cycle') {
            $this->logger->info('Processing subscription renewal invoice', ['stripe_invoice_id' => $stripeInvoice->id]);

            // VERIFICATION IDEMPOTENCE
            $existingInvoice = $this->entityManager->getRepository(Invoice::class)->findOneBy(['stripeInvoiceId' => $stripeInvoice->id]);
            if ($existingInvoice) {
                $this->logger->info("Invoice {$stripeInvoice->id} already processed. Skipping.");
                return;
            }

            // Créer une nouvelle facture locale
            $invoice = new Invoice();
            $invoice->setClient($subscription->getClient());
            $invoice->setSubscription($subscription);
            $invoice->setStripeInvoiceId($stripeInvoice->id);

            // Formatage de la période
            $periodStart = (new \DateTime())->setTimestamp($stripeInvoice->period_start);
            $periodEnd = (new \DateTime())->setTimestamp($stripeInvoice->period_end);

            // Mise à jour immédiate des dates pour assurer la cohérence
            $subscription->setCurrentPeriodStart($periodStart);
            $subscription->setCurrentPeriodEnd($periodEnd);
            $this->entityManager->flush();

            $this->logger->info("Syncing dates for sub {$subscription->getId()} from invoice: start={$periodStart->format('Y-m-d')}, end={$periodEnd->format('Y-m-d')}");

            // Récupérer le companyId depuis la dernière facture du client ou par défaut '1'
            $lastInvoice = $this->entityManager->getRepository(Invoice::class)->findOneBy(['client' => $subscription->getClient()], ['id' => 'DESC']);
            $companyId = $lastInvoice ? $lastInvoice->getCompanyId() : '1';
            $invoice->setCompanyId($companyId);

            $invoice->setStatutEnum(InvoiceStatus::DRAFT);

            // On utilise la date de création de Stripe pour la cohérence (surtout pour les Test Clocks)
            $stripeInvoiceDate = (new \DateTime())->setTimestamp($stripeInvoice->created);
            $invoice->setDateCreation($stripeInvoiceDate);
            $invoice->setDateEcheance((clone $stripeInvoiceDate)->modify('+30 days'));
            $invoice->setNotes('Renouvellement automatique abonnement : ' . $subscription->getLabel());
            $invoice->setConditionsPaiement('Paiement automatique Stripe');

            // Créer la ligne de facture
            $line = new InvoiceLine();

            $periodStr = sprintf(" (Période du %s au %s)", $periodStart->format('d/m/Y'), $periodEnd->format('d/m/Y'));

            $line->setDescription($subscription->getLabel() . ' - Renouvellement' . $periodStr);
            $line->setQuantity(1);
            $line->setUnitPrice($subscription->getAmount()); // Montant en euros

            // Recalculer le total
            $line->recalculateTotalHt();

            // Appliquer la TVA - On suit la logique de Stripe
            // Si Stripe n'a pas appliqué de taxes, on met 0 à la TVA locale pour que les montants correspondent
            $stripeTax = $stripeInvoice->tax ?? 0;
            if (empty($stripeTax) && empty($stripeInvoice->total_tax_amounts)) {
                $line->setTvaRate('0.00');
            } else {
                // Si Stripe a appliqué des taxes, on devrait idéalement récupérer le taux.
                // Pour l'instant on garde le fallback 20% si un tariff est lié, ou 20% par défaut
                $line->setTvaRate('20.00');
            }

            $invoice->addLine($line);
            $invoice->recalculateTotalsFromLines();

            $this->entityManager->persist($invoice);
            $this->entityManager->flush();

            // Emettre et Payer la facture
            try {
                // FORCE BYPASS SECURITY car on est en Webhook (pas d'utilisateur connecté)
                $this->invoiceService->issue($invoice, true);

                // Marquer payée par paiement externe (Stripe)
                $amountPaid = (float) ($stripeInvoice->amount_paid / 100);
                $this->invoiceService->markPaidByExternalPayment($invoice, $amountPaid, false, $stripeInvoiceDate);

                // Mettre à jour les dates de l'abonnement depuis l'objet Stripe Subscription (plus fiable)
                $this->syncSubscriptionStatus($subscription);

                // Logging pour débug
                $this->logger->info("Next deadline set to: " . ($subscription->getCurrentPeriodEnd() ? $subscription->getCurrentPeriodEnd()->format('Y-m-d') : 'NULL'));

                // Envoyer la facture par email (FORCE BYPASS SECURITY)
                // On l'envoie APRES le paiement pour que le client reçoive la version "PAYÉE"
                try {
                    // On utilise directement EmailService pour être sûr de l'envoi
                    $this->emailService->sendInvoice($invoice);

                    // On marque comme envoyé dans l'entité
                    $invoice->setStatutEnum(InvoiceStatus::SENT);
                    $invoice->setDateEnvoi(new \DateTime());
                    $invoice->incrementSentCount();
                    $this->entityManager->flush();
                } catch (\Exception $e) {
                    $this->logger->error('Erreur envoi email invoice renewal: ' . $e->getMessage());
                }

                // Mettre à jour les dates de l'abonnement
                $subscription->setCurrentPeriodStart((new \DateTime())->setTimestamp($stripeInvoice->period_start));
                $subscription->setCurrentPeriodEnd((new \DateTime())->setTimestamp($stripeInvoice->period_end));
                $this->entityManager->flush();

                $this->logger->info('Renewal invoice created, paid and sent', ['invoice_id' => $invoice->getId()]);
            } catch (\Exception $e) {
                $this->logger->error('Error processing renewal invoice: ' . $e->getMessage());
            }
        }
    }

    /**
     * Gère le webhook invoice.payment_failed
     */
    public function handleInvoicePaymentFailed(object $stripeInvoice): void
    {
        $this->logger->warning("Webhook invoice.payment_failed. ID: {$stripeInvoice->id}");

        $invoice = $this->entityManager->getRepository(Invoice::class)->findOneBy(['stripeInvoiceId' => $stripeInvoice->id]);

        if (!$invoice) {
            $this->logger->warning("Invoice local non trouvée pour {$stripeInvoice->id} - Impossible d'envoyer l'email d'échec.");
            return;
        }

        try {
            $actionUrl = $this->magicLinkService->generatePayLink($invoice);
            // Raison simplifiée
            $reason = "Le prélèvement automatique a échoué.";
            if (isset($stripeInvoice->next_payment_attempt)) {
                $next = (new \DateTime())->setTimestamp($stripeInvoice->next_payment_attempt);
                $reason .= " Une nouvelle tentative aura lieu le " . $next->format('d/m/Y');
            }

            $this->emailService->sendPaymentFailed($invoice, $reason, $actionUrl);
            $this->logger->info("Email d'échec envoyé pour la facture {$invoice->getId()}");
        } catch (\Exception $e) {
            $this->logger->error("Erreur envoi email échec: " . $e->getMessage());
        }
    }
}
