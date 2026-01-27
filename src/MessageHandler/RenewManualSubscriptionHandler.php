<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceStatus;
use App\Message\RenewManualSubscriptionMessage;
use App\Repository\SubscriptionRepository;
use App\Service\InvoiceNumberGenerator;
use App\Service\InvoiceService;
use App\Service\EmailService;
use App\Service\PdfGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RenewManualSubscriptionHandler
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly PdfGeneratorService $pdfGeneratorService,
        private readonly InvoiceService $invoiceService,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(RenewManualSubscriptionMessage $message): void
    {
        $subscriptionId = $message->getSubscriptionId();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            $this->logger->error('Abonnement non trouvé pour renouvellement manuel', ['id' => $subscriptionId]);
            return;
        }

        if ($subscription->getStatus() !== 'active') {
            $this->logger->info('Abonnement non actif ignoré pour renouvellement', ['id' => $subscriptionId, 'status' => $subscription->getStatus()]);
            return;
        }

        // Création de la facture de renouvellement
        try {
            $invoice = new Invoice();
            $invoice->setClient($subscription->getClient());

            // Récupérer le companyId depuis la dernière facture du client ou par défaut '1'
            $lastInvoice = $this->entityManager->getRepository(Invoice::class)->findOneBy(['client' => $subscription->getClient()], ['id' => 'DESC']);
            $companyId = $lastInvoice ? $lastInvoice->getCompanyId() : '1';
            $invoice->setCompanyId($companyId);

            $invoice->setStatutEnum(InvoiceStatus::DRAFT);

            // Dates
            $now = new \DateTime();
            $invoice->setDateEcheance((clone $now)->modify('+30 days'));

            // Ligne de facture
            $line = new InvoiceLine();
            $description = "Renouvellement abonnement : " . $subscription->getLabel();

            // Période couvrant le renouvellement
            $newPeriodStart = clone $subscription->getCurrentPeriodEnd();
            // Si la date est passée, on part d'aujourd'hui ou on rattrape ?
            // Pour simplifier, on prend la suite logique de l'abonnement
            if ($newPeriodStart < $now) {
                // Si très en retard, on aligne peut-être à aujourd'hui ?
                // Gardons la logique de continuité pour l'instant.
            }

            $newPeriodEnd = clone $newPeriodStart;
            if ($subscription->getIntervalUnit() === 'month') {
                $newPeriodEnd->modify('+1 month');
                $description .= sprintf(" (Période du %s au %s)", $newPeriodStart->format('d/m/Y'), $newPeriodEnd->format('d/m/Y'));
            } else {
                $newPeriodEnd->modify('+1 year');
                $description .= sprintf(" (Période du %s au %s)", $newPeriodStart->format('d/m/Y'), $newPeriodEnd->format('d/m/Y'));
            }

            $line->setDescription($description);
            $line->setQuantity(1);
            $line->setUnitPrice((string) ($subscription->getAmount() ?? 0));
            // Important : Marquer cette ligne comme abonnement aussi pour le prochain cycle !
            $line->setSubscriptionMode($subscription->getIntervalUnit() === 'month' ? 'monthly' : 'yearly');
            $line->setRecurrenceAmount($subscription->getAmount());

            // TVA : On récupère les paramètres de l'entreprise
            $companySettings = $this->entityManager->getRepository(\App\Entity\CompanySettings::class)->findOneBy([]);
            if ($companySettings && $companySettings->isTvaEnabled()) {
                // On utilise le taux par défaut du devis source si possible, ou 20%
                $tvaRate = '20.00';
                if ($subscription->getTariff() && $subscription->getTariff()->getTauxTva()) {
                    $tvaRate = $subscription->getTariff()->getTauxTva();
                }
                $line->setTvaRate($tvaRate);
            } else {
                $line->setTvaRate('0.00');
            }
            $line->recalculateTotalHt();

            $invoice->addLine($line);
            $invoice->recalculateTotalsFromLines();

            $this->entityManager->persist($invoice);
            $this->entityManager->flush();

            // 2. Émettre la facture (Génère le numéro et le PDF)
            $this->invoiceService->issue($invoice, true);

            // 3. Envoyer la facture au client
            $this->invoiceService->send($invoice, 'email', true);
            $this->emailService->sendInvoice($invoice);

            // 4. Mise à jour des dates de l'abonnement
            $subscription->setCurrentPeriodStart($newPeriodStart);
            $subscription->setCurrentPeriodEnd($newPeriodEnd);

            $this->entityManager->flush();

            $this->logger->info('Facture de renouvellement émise et envoyée', [
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoice->getId(),
                'invoice_number' => $invoice->getNumero(),
                'new_period_end' => $newPeriodEnd->format('Y-m-d')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération de facture renouvellement', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
