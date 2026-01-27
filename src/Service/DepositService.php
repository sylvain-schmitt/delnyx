<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Deposit;
use App\Entity\DepositStatus;
use App\Entity\Invoice;
use App\Entity\Quote;
use App\Repository\DepositRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des accomptes
 */
class DepositService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DepositRepository $depositRepository,
        private readonly PaymentService $paymentService,
        private readonly AuditService $auditService,
        private readonly PdfGeneratorService $pdfGeneratorService,
        private readonly LoggerInterface $logger,
        private readonly StripeService $stripeService,
    ) {}

    /**
     * Crée un accompte pour un devis signé
     *
     * @param Quote $quote Devis signé
     * @param float $percentage Pourcentage du devis (ex: 30.0)
     * @param int|null $amountInCents Montant en centimes (prioritaire sur le pourcentage si fourni)
     * @throws \RuntimeException Si le devis ne peut pas recevoir d'accompte
     */
    public function createDeposit(Quote $quote, float $percentage = Deposit::DEFAULT_PERCENTAGE, ?int $amountInCents = null): Deposit
    {
        // Vérifier que le devis peut recevoir un accompte
        if (!$quote->canRequestDeposit()) {
            throw new \RuntimeException('Ce devis ne peut pas recevoir d\'accompte (doit être signé et non facturé).');
        }

        $montantTTC = (float) $quote->getMontantTTC();

        // Si un montant en centimes est fourni, l'utiliser et recalculer le pourcentage
        if ($amountInCents !== null && $amountInCents > 0) {
            $percentage = ($amountInCents / 100 / $montantTTC) * 100;
        } else {
            // Sinon, calculer le montant à partir du pourcentage
            $amountInCents = (int) round($montantTTC * ($percentage / 100) * 100);
        }

        $deposit = new Deposit();
        $deposit->setQuote($quote);
        $deposit->setPercentage($percentage);
        $deposit->setAmount($amountInCents);
        $deposit->setStatus(DepositStatus::PENDING);

        $this->entityManager->persist($deposit);
        $this->entityManager->flush();

        // Audit
        $this->auditService->log(
            entityType: 'Deposit',
            entityId: $deposit->getId(),
            action: 'create',
            metadata: [
                'quote_id' => $quote->getId(),
                'quote_numero' => $quote->getNumero(),
                'percentage' => $percentage,
                'amount_cents' => $amountInCents,
            ]
        );

        $this->logger->info('Accompte créé', [
            'deposit_id' => $deposit->getId(),
            'quote_id' => $quote->getId(),
            'percentage' => $percentage,
            'amount' => $amountInCents / 100,
        ]);

        return $deposit;
    }

    /**
     * Crée une session Stripe pour payer l'accompte
     */
    public function createPaymentSession(Deposit $deposit, string $successUrl, string $cancelUrl): string
    {
        if ($deposit->getStatus() !== DepositStatus::PENDING) {
            throw new \RuntimeException('Cet accompte ne peut plus être payé.');
        }

        if (!$this->stripeService->isConfigured()) {
            throw new \RuntimeException('Stripe n\'est pas configuré.');
        }

        \Stripe\Stripe::setApiKey($this->stripeService->getSecretKey());

        $quote = $deposit->getQuote();
        $client = $quote->getClient();

        // S'assurer que le client Stripe existe
        $customerId = null;
        if ($client) {
            try {
                $customerId = $this->stripeService->ensureStripeCustomer($client);
            } catch (\Exception $e) {
                $this->logger->error('Impossible de créer le client Stripe pour l\'acompte: ' . $e->getMessage());
                // On continue sans customerId (mode guest), mais on log l'erreur
            }
        }

        $sessionParams = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => sprintf('Accompte - Devis %s', $quote->getNumero()),
                        'description' => sprintf(
                            'Accompte de %s%% sur le devis %s',
                            number_format($deposit->getPercentage(), 0),
                            $quote->getNumero()
                        ),
                    ],
                    'unit_amount' => $deposit->getAmount(),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => sprintf('deposit_%d', $deposit->getId()),
            'metadata' => [
                'deposit_id' => $deposit->getId(),
                'quote_id' => $quote->getId(),
                'quote_numero' => $quote->getNumero(),
                'client_id' => $client?->getId(),
                'type' => 'deposit',
            ],
        ];

        if ($customerId) {
            $sessionParams['customer'] = $customerId;
            // Un client Stripe ne peut pas être défini en même temps que customer_email
        } elseif ($client?->getEmail()) {
            $sessionParams['customer_email'] = $client->getEmail();
        }

        $session = \Stripe\Checkout\Session::create($sessionParams);

        // Enregistrer l'ID de session
        $deposit->setStripeSessionId($session->id);
        $this->entityManager->flush();

        $this->logger->info('Session Stripe créée pour accompte', [
            'deposit_id' => $deposit->getId(),
            'session_id' => $session->id,
        ]);

        return $session->url;
    }

    /**
     * Marque l'accompte comme payé (appelé après retour Stripe ou webhook)
     *
     * Cette méthode crée automatiquement une facture d'acompte (obligation légale CGI Art. 289)
     */
    public function markPaid(Deposit $deposit, ?string $paymentIntentId = null): Invoice
    {
        if ($deposit->getStatus() === DepositStatus::PAID) {
            // Déjà payé, retourner la facture existante si elle existe
            return $deposit->getDepositInvoice() ?? throw new \RuntimeException('Acompte déjà payé mais facture introuvable');
        }

        $deposit->setStatus(DepositStatus::PAID);
        $deposit->setPaidAt(new \DateTimeImmutable());

        if ($paymentIntentId) {
            $deposit->setStripePaymentIntentId($paymentIntentId);
        }

        // Récupérer ou créer la facture d'acompte
        $depositInvoice = $this->getOrCreateDepositInvoice($deposit, \App\Entity\InvoiceStatus::PAID);

        // Si la facture existait déjà mais n'était pas payée, on la met à jour
        if ($depositInvoice->getStatut() !== \App\Entity\InvoiceStatus::PAID->value) {
            $depositInvoice->setStatut(\App\Entity\InvoiceStatus::PAID->value);
            $depositInvoice->setDatePaiement(new \DateTime());
            $depositInvoice->setConditionsPaiement('Acompte reçu');
        }

        $this->entityManager->flush();

        // Audit
        $this->auditService->log(
            entityType: 'Deposit',
            entityId: $deposit->getId(),
            action: 'paid',
            metadata: [
                'payment_intent_id' => $paymentIntentId,
                'amount' => $deposit->getAmountInEuros(),
                'deposit_invoice_id' => $depositInvoice->getId(),
                'deposit_invoice_numero' => $depositInvoice->getNumero(),
            ]
        );

        $this->logger->info('Accompte marqué comme payé, facture d\'acompte générée', [
            'deposit_id' => $deposit->getId(),
            'amount' => $deposit->getAmountInEuros(),
            'deposit_invoice_id' => $depositInvoice->getId(),
        ]);

        // Générer et sauvegarder le PDF avec hash pour archivage légal (après flush car on a besoin de l'ID)
        $this->saveDepositInvoicePdf($depositInvoice, $deposit);

        return $depositInvoice;
    }

    /**
     * Récupère la facture d'acompte existante ou en crée une nouvelle
     */
    public function getOrCreateDepositInvoice(Deposit $deposit, \App\Entity\InvoiceStatus $status = \App\Entity\InvoiceStatus::ISSUED): Invoice
    {
        $invoice = $deposit->getDepositInvoice();

        if (!$invoice) {
            $invoice = $this->createDepositInvoice($deposit, $status);
            $this->entityManager->flush();

            // Générer le PDF initial
            $this->saveDepositInvoicePdf($invoice, $deposit);
        }

        return $invoice;
    }

    /**
     * Génère et sauvegarde le PDF de la facture d'acompte avec hash SHA256
     */
    public function saveDepositInvoicePdf(Invoice $invoice, Deposit $deposit): void
    {
        try {
            // Générer le PDF avec sauvegarde
            $pdfResult = $this->pdfGeneratorService->generateFacturePdf($invoice, true);

            if (is_array($pdfResult) && isset($pdfResult['filename'], $pdfResult['hash'])) {
                $invoice->setPdfFilename($pdfResult['filename']);
                $invoice->setPdfHash($pdfResult['hash']);
                $this->entityManager->flush();

                $this->logger->info('PDF facture d\'acompte sauvegardé', [
                    'invoice_id' => $invoice->getId(),
                    'filename' => $pdfResult['filename'],
                    'hash' => $pdfResult['hash'],
                ]);
            }
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas le processus
            $this->logger->error('Erreur lors de la sauvegarde du PDF de facture d\'acompte', [
                'invoice_id' => $invoice->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Crée une facture d'acompte pour un paiement reçu
     * (Obligation légale : CGI Article 289)
     */
    public function createDepositInvoice(Deposit $deposit, \App\Entity\InvoiceStatus $status = \App\Entity\InvoiceStatus::PAID): Invoice
    {
        $quote = $deposit->getQuote();
        $client = $quote->getClient();

        // Calculer la TVA de l'acompte proportionnellement
        $montantTTC = $deposit->getAmountInEuros();
        $tauxTVA = $quote->getTauxTVA() ?? 20.0;
        $montantHT = $montantTTC / (1 + ($tauxTVA / 100));
        $montantTVA = $montantTTC - $montantHT;

        $invoice = new Invoice();
        $invoice->setType(\App\Entity\InvoiceType::DEPOSIT);
        $invoice->setSourceDeposit($deposit);
        // Note: On ne définit PAS setQuote() ici car la relation OneToOne
        // bloquerait la création de la facture finale. Le devis est accessible via
        // $invoice->getSourceDeposit()->getQuote()
        $invoice->setClient($client);
        $invoice->setCompanyId($quote->getCompanyId());

        // Montants
        $invoice->setMontantHT(number_format($montantHT, 2, '.', ''));
        $invoice->setMontantTVA(number_format($montantTVA, 2, '.', ''));
        $invoice->setMontantTTC(number_format($montantTTC, 2, '.', ''));

        // Dates et statut
        $invoice->setDateCreation(new \DateTime());

        if ($status === \App\Entity\InvoiceStatus::PAID) {
            $invoice->setDateEcheance(new \DateTime());
            $invoice->setStatut($status->value);
            $invoice->setDatePaiement(new \DateTime());
            $invoice->setConditionsPaiement('Acompte reçu');
        } else {
            // Échéance à J+15 pour les acomptes en attente
            $dateEcheance = (new \DateTime())->modify('+15 days');
            $invoice->setDateEcheance($dateEcheance);
            $invoice->setStatut($status->value);
            $invoice->setConditionsPaiement('En attente de paiement');
        }

        // Conditions
        $invoice->setNotes(sprintf(
            "Facture d'acompte pour le devis %s\nPourcentage : %s%%",
            $quote->getNumero(),
            number_format($deposit->getPercentage(), 0)
        ));

        $this->entityManager->persist($invoice);

        // Le numéro sera généré automatiquement par le listener/subscriber si configuré
        // Sinon on le génère ici
        if (!$invoice->getNumero()) {
            $invoice->setNumero($this->generateDepositInvoiceNumber($quote->getCompanyId()));
        }

        // Note: L'audit log sera fait dans markPaid() après le flush
        // car l'ID n'est pas encore disponible ici

        return $invoice;
    }

    /**
     * Génère un numéro de facture d'acompte
     */
    private function generateDepositInvoiceNumber(string $companyId): string
    {
        $year = date('Y');
        $prefix = 'FA'; // FA pour Facture d'Acompte

        // Trouver le dernier numéro de facture de l'année
        $lastInvoice = $this->entityManager
            ->getRepository(Invoice::class)
            ->createQueryBuilder('i')
            ->where('i.companyId = :companyId')
            ->andWhere('i.numero LIKE :pattern')
            ->setParameter('companyId', $companyId)
            ->setParameter('pattern', $prefix . '-' . $year . '-%')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastInvoice) {
            // Extraire le numéro séquentiel
            $parts = explode('-', $lastInvoice->getNumero());
            $sequence = (int) end($parts) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    /**
     * Déduit les accomptes payés d'une facture
     *
     * @param Invoice $invoice Facture à laquelle déduire
     * @param Quote $quote Devis source
     */
    public function deductDepositsToInvoice(Invoice $invoice, Quote $quote): void
    {
        // On déduit tous les acomptes sauf ceux annulés (PAID ou ISSUED/PENDING)
        $deposits = $this->depositRepository->createQueryBuilder('d')
            ->andWhere('d.quote = :quote')
            ->andWhere('d.status NOT IN (:excludedStatuses)')
            ->andWhere('d.invoice IS NULL')
            ->setParameter('quote', $quote)
            ->setParameter('excludedStatuses', [DepositStatus::CANCELLED->value])
            ->getQuery()
            ->getResult();

        foreach ($deposits as $deposit) {
            $deposit->markAsDeducted($invoice);

            $this->auditService->log(
                entityType: 'Deposit',
                entityId: $deposit->getId(),
                action: 'deducted',
                metadata: [
                    'invoice_id' => $invoice->getId(),
                    'invoice_numero' => $invoice->getNumero(),
                    'amount' => $deposit->getAmountInEuros(),
                ]
            );

            $this->logger->info('Accompte déduit sur facture', [
                'deposit_id' => $deposit->getId(),
                'invoice_id' => $invoice->getId(),
                'amount' => $deposit->getAmountInEuros(),
            ]);
        }

        $this->entityManager->flush();
    }

    /**
     * Annule un accompte en attente
     */
    public function cancel(Deposit $deposit, ?string $reason = null): void
    {
        if (!$deposit->getStatus()->canBeCancelled()) {
            throw new \RuntimeException('Cet accompte ne peut plus être annulé.');
        }

        $deposit->setStatus(DepositStatus::CANCELLED);
        if ($reason) {
            $deposit->setNotes($reason);
        }

        $this->entityManager->flush();

        $this->auditService->log(
            entityType: 'Deposit',
            entityId: $deposit->getId(),
            action: 'cancelled',
            metadata: ['reason' => $reason]
        );
    }

    /**
     * Trouve un accompte par son ID de session Stripe
     */
    public function findByStripeSession(string $sessionId): ?Deposit
    {
        return $this->depositRepository->findByStripeSessionId($sessionId);
    }
}
