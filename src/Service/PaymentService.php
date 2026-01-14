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
        private string $stripeSecretKey = '',
    ) {
    }

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
        if (empty($this->stripeSecretKey)) {
            // Pour le développement sans clé Stripe, on simule une erreur ou on retourne une URL fictive
            // Mais en prod c'est critique.
            // Si on est en dev et pas de clé, on peut throw une exception explicite
            if ($_SERVER['APP_ENV'] === 'dev' && empty($this->stripeSecretKey)) {
                 throw new \RuntimeException('Stripe Secret Key manquante. Veuillez configurer STRIPE_SECRET_KEY dans .env.local');
            }
            throw new \RuntimeException('Configuration de paiement manquante.');
        }

        \Stripe\Stripe::setApiKey($this->stripeSecretKey);

        try {
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => 'Facture ' . $invoice->getNumero(),
                            'description' => 'Paiement de la facture ' . $invoice->getNumero() . ' pour ' . $invoice->getClient()->getNomComplet(),
                        ],
                        'unit_amount' => (int) round((float) $invoice->getMontantTTC() * 100), // Montant en centimes
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $invoice->getId(),
                'metadata' => [
                    'invoice_id' => $invoice->getId(),
                    'invoice_number' => $invoice->getNumero(),
                    'client_email' => $invoice->getClient()->getEmail(),
                ],
                'customer_email' => $invoice->getClient()->getEmail(),
            ]);

            // Créer le paiement en base avec statut PENDING
            $payment = new Payment();
            $payment->setInvoice($invoice);
            $payment->setAmountFromEuros((float) $invoice->getMontantTTC());
            $payment->setCurrency('EUR');
            $payment->setProvider(PaymentProvider::STRIPE);
            $payment->setStatus(PaymentStatus::PENDING);
            $payment->setProviderPaymentId($session->payment_intent ?? $session->id); // On stocke l'ID de session temporairement si payment_intent est null (mode payment)
            $payment->setMetadata([
                'checkout_session_id' => $session->id,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
            
            $this->entityManager->persist($payment);
            $this->entityManager->flush();

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
                $this->invoiceService->markPaid($invoice, $payment->getAmountInEuros());
                
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

        if (!$payment) {
            $this->logger->error('Payment not found for failure', ['payment_intent_id' => $paymentIntentId]);
            return;
        }

        $payment->setStatus(PaymentStatus::FAILED);
        $payment->setFailureReason($reason);
        $this->entityManager->flush();

        $this->logger->warning('Payment failed', [
            'payment_id' => $payment->getId(),
            'invoice_id' => $payment->getInvoice()->getId(),
            'reason' => $reason,
        ]);
    }

    /**
     * Rembourse un paiement
     *
     * @param int $invoiceId ID de la facture
     * @param float $amount Montant à rembourser (en euros), null = remboursement complet
     * @return Payment Paiement de remboursement
     */
    public function refundPayment(int $invoiceId, ?float $amount = null): Payment
    {
        // TODO: Implémenter avec Stripe SDK dans Sprint 3
        // 1. Récupérer les paiements réussis de la facture
        // 2. Créer un Refund chez Stripe
        // 3. Créer Payment avec status=REFUNDED
        // 4. Mettre à jour le statut de la facture si remboursement complet

        throw new \RuntimeException('Refund not implemented yet - Sprint 3');
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
        $payment->setAmountFromEuros((float) $invoice->getMontantTTC());
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
