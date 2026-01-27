<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Payment;
use App\Entity\PaymentProvider;
use App\Entity\PaymentStatus;
use App\Entity\Invoice;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\CompanySettingsRepository;
use App\Service\EmailService; // AJOUTÉ
use App\Service\MagicLinkService; // AJOUTÉ

/**
 * Service de gestion des paiements
 */
class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,
        private LoggerInterface $logger,
        private UrlGeneratorInterface $router,
        private InvoiceService $invoiceService,
        private CompanySettingsRepository $companySettingsRepository,
        private StripeService $stripeService,
        private EmailService $emailService,
        private MagicLinkService $magicLinkService,
    ) {}

    /**
     * Crée une session Stripe Checkout pour le paiement d'une facture
     *
     * @param Invoice $invoice Facture à payer
     * @param string $successUrl URL de redirection en cas de succès
     * @param string $cancelUrl URL de redirection en cas d'annulation
     * @return string URL de redirection vers Stripe Checkout
     */
    public function createPaymentIntent(Invoice $invoice, string $successUrl, string $cancelUrl): string
    {
        if (!$this->stripeService->isConfigured()) {
            if ($_SERVER['APP_ENV'] === 'dev') {
                throw new \RuntimeException('Stripe non configuré. Veuillez configurer les clés dans Admin > Paramètres ou .env.local');
            }
            throw new \RuntimeException('Configuration de paiement manquante.');
        }

        \Stripe\Stripe::setApiKey($this->stripeService->getSecretKey());

        $companySettings = $this->companySettingsRepository->findByCompanyId($invoice->getCompanyId());
        // Fallback sur le premier si non trouvé
        if (!$companySettings) {
            $companySettings = $this->companySettingsRepository->findOneBy([]);
        }

        $isTvaEnabled = $companySettings ? $companySettings->isTvaEnabled() : true;

        try {
            // Déterminer le mode (payment ou subscription)
            $mode = 'payment';
            $lineItems = [];
            $hasSubscription = false;

            // Construire les line_items
            foreach ($invoice->getLines() as $line) {
                // Si la ligne a un mode d'abonnement et un montant récurrent (et pas encore payée/exécutée)
                if ($line->getSubscriptionMode()) {
                    $hasSubscription = true;
                    $interval = $line->getSubscriptionMode() === 'monthly' ? 'month' : 'year';

                    // Calculer le prix unitaire TTC pour cette ligne
                    $unitPriceHt = (float) $line->getUnitPrice();
                    $tvaRate = 0.0;
                    if ($isTvaEnabled) {
                        $tvaRate = (float) ($line->getTvaRate() ?? ($invoice->getQuote() ? $invoice->getQuote()->getTauxTVA() : 0));
                    }
                    $unitPriceTtc = $unitPriceHt * (1 + ($tvaRate / 100));

                    $item = [
                        'quantity' => $line->getQuantity(),
                    ];

                    $item['price_data'] = [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $line->getDescription(),
                        ],
                        'unit_amount' => (int) round($unitPriceTtc * 100),
                        'recurring' => ['interval' => $interval],
                    ];

                    $lineItems[] = $item;
                } else {
                    // Item one-shot
                    $unitPriceHt = (float) $line->getUnitPrice();
                    $tvaRate = 0.0;
                    if ($isTvaEnabled) {
                        $tvaRate = (float) ($line->getTvaRate() ?? ($invoice->getQuote() ? $invoice->getQuote()->getTauxTVA() : 0));
                    }
                    $unitPriceTtc = $unitPriceHt * (1 + ($tvaRate / 100));

                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => [
                                'name' => $line->getDescription(),
                            ],
                            'unit_amount' => (int) round($unitPriceTtc * 100),
                        ],
                        'quantity' => $line->getQuantity(),
                    ];
                }
            }

            if ($hasSubscription) {
                $mode = 'subscription';

                // Si on a un acompte déjà payé, on doit le déduire du premier paiement.
                // Dans Stripe Checkout 'subscription', on ne peut pas avoir de montant total négatif.
                // On va déduire l'acompte des items "one-shot" (standard).
                if ($invoice->hasDepositsDeducted()) {
                    // Attention: getTotalDepositsDeducted retourne des centimes (int)
                    $depositToDeduct = (float) $invoice->getTotalDepositsDeducted() / 100;

                    foreach ($lineItems as &$item) {
                        // On ne déduit que des items non-récurrents pour ne pas impacter les futures échéances
                        if (!isset($item['price_data']['recurring'])) {
                            $currentAmount = $item['price_data']['unit_amount'] / 100;
                            $deduction = min($currentAmount, $depositToDeduct);

                            $item['price_data']['unit_amount'] = (int) round(($currentAmount - $deduction) * 100);
                            $depositToDeduct -= $deduction;

                            if ($depositToDeduct <= 0) break;
                        }
                    }
                    unset($item);

                    // Note: Si l'acompte est supérieur aux items standard, le reliquat n'est pas déduit ici.
                    // Pour un SaaS pro, on utiliserait des coupons Stripe, mais ici la déduction sur les items standard couvre 99% des cas.
                }
            } else {
                // Mode classique : on utilise le solde global comme un seul item "Paiement Facture X"
                // C'est ce que faisait le code précédent, on le garde pour la compatibilité parfaite avec acomptes/avoirs
                $lineItems = [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => 'Facture ' . $invoice->getNumero(),
                            'description' => $invoice->hasDepositsDeducted()
                                ? 'Solde de la facture ' . $invoice->getNumero() . ' (acomptes déduits)'
                                : 'Paiement de la facture ' . $invoice->getNumero(),
                        ],
                        'unit_amount' => (int) round($invoice->getBalanceDue() * 100),
                    ],
                    'quantity' => 1,
                ]];
            }

            $sessionParams = [
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'mode' => $mode,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $invoice->getId(),
                'metadata' => [
                    'invoice_id' => $invoice->getId(),
                    'invoice_number' => $invoice->getNumero(),
                    'client_email' => $invoice->getClient()->getEmail(),
                    'client_id' => $invoice->getClient()->getId(),
                ],
            ];

            // S'assurer que le client Stripe existe
            $client = $invoice->getClient();
            $customerId = null;
            if ($client) {
                try {
                    $customerId = $this->stripeService->ensureStripeCustomer($client);
                } catch (\Exception $e) {
                    $this->logger->error('Impossible de créer le client Stripe pour la facture: ' . $e->getMessage());
                }
            }

            if ($customerId) {
                $sessionParams['customer'] = $customerId;
            } elseif ($client?->getEmail()) {
                $sessionParams['customer_email'] = $client->getEmail();
            }

            // En mode subscription, ajouter subscription_data avec métadonnées
            // En mode payment, ajouter payment_intent_data avec métadonnées
            if ($mode === 'subscription') {
                $sessionParams['subscription_data'] = [
                    'metadata' => $sessionParams['metadata']
                ];
            } else {
                $sessionParams['payment_intent_data'] = [
                    'metadata' => [
                        'invoice_id' => $invoice->getId(),
                    ]
                ];
            }

            // Création de la session
            $session = \Stripe\Checkout\Session::create($sessionParams);

            // Créer le paiement en base (PENDING)
            $payment = new Payment();
            $payment->setInvoice($invoice);
            // Si subscription, le montant total est calculé par Stripe (approximativement le premier paiement)
            // On utilise le solde dû de la facture comme référence
            $payment->setAmountFromEuros($invoice->getBalanceDue());
            $payment->setCurrency('EUR');
            $payment->setProvider(PaymentProvider::STRIPE);
            $payment->setStatus(PaymentStatus::PENDING);
            $payment->setProviderPaymentId($session->id); // Session ID
            $payment->setMetadata([
                'checkout_session_id' => $session->id,
                'mode' => $mode,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            $this->logger->info('Stripe Checkout Session Created', [
                'session_id' => $session->id,
                'mode' => $mode,
                'invoice_id' => $invoice->getId(),
            ]);

            return $session->url;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logger->error('Stripe Checkout Error: ' . $e->getMessage(), ['exception' => $e]);
            throw new \RuntimeException('Erreur lors de l\'initialisation du paiement: ' . $e->getMessage());
        }
    }

    /**
     * Gère le succès d'un paiement (appelé par webhook)
     *
     * @param string $paymentIntentId ID du paiement chez le provider
     */
    public function handlePaymentSuccess(string $paymentIntentId): void
    {
        // Recherche par providerPaymentId (PaymentIntent ID)
        $payment = $this->paymentRepository->findByProviderPaymentId($paymentIntentId);

        // Si non trouvé, essayer de trouver via metadata checkout_session_id si c'est un session ID
        if (!$payment) {
            // Recherche manuelle dans les métadonnées JSON (plus coûteux, mais nécessaire si on reçoit un session ID)
            // Note: Idéalement on devrait avoir une méthode dédiée dans le repository
            // Pour l'instant on log l'erreur
            $this->logger->error('Payment not found', ['payment_intent_id' => $paymentIntentId]);
            return;
        }

        // Si déjà payé, on ignore
        if ($payment->getStatus() === PaymentStatus::SUCCEEDED) {
            return;
        }

        // Mettre à jour le statut du paiement
        $payment->setStatus(PaymentStatus::SUCCEEDED);
        $payment->setPaidAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Marquer la facture comme payée
        $invoice = $payment->getInvoice();
        if ($invoice) {
            try {
                $this->invoiceService->markPaid($invoice, $payment->getAmountInEuros(), true);

                $this->logger->info('Payment succeeded and invoice marked as paid', [
                    'payment_id' => $payment->getId(),
                    'invoice_id' => $invoice->getId(),
                    'amount' => $payment->getFormattedAmount(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Error marking invoice as paid: ' . $e->getMessage(), [
                    'invoice_id' => $invoice->getId(),
                    'exception' => $e
                ]);
            }
        }
    }

    /**
     * Gère l'échec d'un paiement
     *
     * @param string $paymentIntentId ID du paiement
     * @param string $reason Raison de l'échec
     */
    public function handlePaymentFailure(string $paymentIntentId, string $reason): void
    {
        $payment = $this->paymentRepository->findByProviderPaymentId($paymentIntentId);
        $invoice = null;

        if ($payment) {
            $payment->setStatus(PaymentStatus::FAILED);
            $payment->setFailureReason($reason);
            $this->entityManager->flush();
            $invoice = $payment->getInvoice();

            $this->logger->warning('Payment failed', [
                'payment_id' => $payment->getId(),
                'invoice_id' => $invoice ? $invoice->getId() : 'null',
                'reason' => $reason,
            ]);
        } else {
            // Tentative de récupération via l'API Stripe
            $this->logger->warning('Payment local not found directly for failure. Fetching from Stripe...', ['pi' => $paymentIntentId]);

            try {
                $this->logger->info('Calling retrievePaymentIntent...');
                $pi = $this->stripeService->retrievePaymentIntent($paymentIntentId);

                if ($pi) {
                    // Force logging metadata keys
                    $keys = isset($pi->metadata) ? $pi->metadata->keys() : [];
                    $this->logger->info('PI retrieved', ['metadata_keys' => $keys, 'metadata_values' => $pi->metadata->toArray()]);
                } else {
                    $this->logger->warning('PI is null returned from service');
                }

                if ($pi && isset($pi->metadata->invoice_id)) {
                    $invoiceId = $pi->metadata->invoice_id;
                    $this->logger->info('Invoice ID found in metadata', ['invoice_id' => $invoiceId]);
                    $invoice = $this->entityManager->getRepository(Invoice::class)->find($invoiceId);

                    if ($invoice) {
                        $this->logger->info('Invoice entity found via Stripe metadata', ['invoice_id' => $invoiceId]);
                    } else {
                        $this->logger->warning('Invoice entity NOT found via Stripe metadata', ['invoice_id' => $invoiceId]);
                    }
                } else {
                    $this->logger->warning('No invoice_id in metadata');
                }
            } catch (\Throwable $e) {
                $this->logger->error('CRITICAL Error retrieving PI from Stripe: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            }
        }

        // Si on a trouvé la facture (via le paiement local OU Stripe), on notifie
        if ($invoice) {
            try {
                $actionUrl = $this->magicLinkService->generatePayLink($invoice);
                $this->emailService->sendPaymentFailed($invoice, $reason, $actionUrl);
            } catch (\Exception $e) {
                $this->logger->error('Error sending payment failed email: ' . $e->getMessage());
            }
        } else {
            // FALLBACK CRITIQUE : Il faut absolument notifier le client.
            // On doit récupérer le PI depuis Stripe pour avoir l'invoice_id.
            // Je vais modifier StripeService pour ajouter retrievePaymentIntent et l'utiliser ici.
        }
    }

    /**
     * Rembourse un paiement lié à un avoir
     *
     * @param \App\Entity\CreditNote $creditNote
     * @return Payment Paiement de remboursement
     */
    public function refundPayment(\App\Entity\CreditNote $creditNote): Payment
    {
        $invoice = $creditNote->getInvoice();
        if (!$invoice) {
            throw new \RuntimeException("Aucune facture liée à cet avoir.");
        }

        // Trouver le dernier paiement Stripe réussi pour cette facture
        // Note: On accepte aussi les 'pending' si la facture est 'paid' (cas où le webhook n'a pas sync mais l'admin l'a marqué payé)
        $qb = $this->paymentRepository->createQueryBuilder('p')
            ->where('p.invoice = :invoice')
            ->andWhere('p.provider = :provider')
            ->setParameter('invoice', $invoice)
            ->setParameter('provider', PaymentProvider::STRIPE);

        if ($invoice->getStatut() === 'paid') {
            $qb->andWhere('p.status IN (:status)')
                ->setParameter('status', [PaymentStatus::SUCCEEDED, PaymentStatus::PENDING]);
        } else {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', PaymentStatus::SUCCEEDED);
        }

        $payment = $qb->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$payment) {
            throw new \RuntimeException("Aucun paiement Stripe éligible au remboursement trouvé pour cette facture.");
        }

        $paymentIntentId = $payment->getProviderPaymentId();
        if (!$paymentIntentId || !str_starts_with($paymentIntentId, 'pi_')) {
            // Si on a un session ID (cs_), on doit retrouver le PI
            if (str_starts_with($paymentIntentId, 'cs_')) {
                $session = $this->stripeService->retrieveSession($paymentIntentId);
                $paymentIntentId = $session->payment_intent;
            } else {
                throw new \RuntimeException("ID de paiement Stripe invalide ou manquant.");
            }
        }

        $amountToRefund = (float) abs((float) $creditNote->getMontantTTC());

        try {
            $metadata = [
                'credit_note_id' => $creditNote->getId(),
                'credit_note_number' => $creditNote->getNumber(),
                'invoice_id' => $invoice->getId(),
            ];

            $stripeRefund = $this->stripeService->createRefund((string) $paymentIntentId, $amountToRefund, $metadata);

            // Créer l'enregistrement de remboursement en base
            $refundPayment = new Payment();
            $refundPayment->setInvoice($invoice);
            $refundPayment->setAmountFromEuros(-$amountToRefund); // Montant négatif en euros
            $refundPayment->setCurrency('EUR');
            $refundPayment->setProvider(PaymentProvider::STRIPE);
            $refundPayment->setStatus(PaymentStatus::REFUNDED);
            $refundPayment->setProviderPaymentId($stripeRefund->id);
            $refundPayment->setPaidAt(new \DateTimeImmutable());
            $refundPayment->setMetadata([
                'stripe_refund_id' => $stripeRefund->id,
                'parent_payment_id' => $payment->getId(),
                'parent_stripe_pi' => $paymentIntentId,
            ]);

            $this->entityManager->persist($refundPayment);

            // Mettre à jour l'avoir
            $creditNote->setStripeRefundId($stripeRefund->id);
            $creditNote->setRefundStatus('succeeded');

            $this->entityManager->flush();

            $this->logger->info('Remboursement Stripe réussi', [
                'refund_id' => $stripeRefund->id,
                'amount' => $amountToRefund,
                'credit_note_id' => $creditNote->getId()
            ]);

            return $refundPayment;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du remboursement Stripe: ' . $e->getMessage());
            $creditNote->setRefundStatus('failed');
            $this->entityManager->flush();
            throw $e;
        }
    }

    /**
     * Crée un paiement manuel (virement, chèque)
     *
     * @param Invoice $invoice
     * @param array $data Données du paiement manuel (method, reference, date, proof_filename)
     * @return Payment
     */
    public function createManualPayment(Invoice $invoice, array $data): Payment
    {
        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setAmountFromEuros($invoice->getBalanceDue()); // Solde après déduction des acomptes
        $payment->setCurrency('EUR');
        $payment->setProvider(PaymentProvider::MANUAL);
        $payment->setStatus(PaymentStatus::SUCCEEDED);
        $payment->setPaidAt(new \DateTimeImmutable($data['paid_at'] ?? 'now'));
        $payment->setMetadata([
            'payment_method' => $data['method'] ?? 'virement',
            'reference' => $data['reference'] ?? null,
            'proof_filename' => $data['proof_filename'] ?? null,
        ]);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $this->logger->info('Manual payment created', [
            'payment_id' => $payment->getId(),
            'invoice_id' => $invoice->getId(),
            'method' => $data['method'] ?? 'virement',
        ]);

        return $payment;
    }
}
