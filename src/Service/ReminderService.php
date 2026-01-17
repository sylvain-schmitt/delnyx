<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\ReminderRule;
use App\Message\SendReminderMessage;
use App\Repository\InvoiceRepository;
use App\Repository\ReminderRepository;
use App\Repository\ReminderRuleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service pour gérer les relances automatiques des factures
 */
class ReminderService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private ReminderRuleRepository $reminderRuleRepository,
        private ReminderRepository $reminderRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {}

    /**
     * Vérifie toutes les factures en retard et dispatche les messages de relance
     *
     * @return array{checked: int, dispatched: int, skipped: int}
     */
    public function processReminders(?string $companyId = null): array
    {
        $stats = ['checked' => 0, 'dispatched' => 0, 'skipped' => 0];

        // Récupérer les règles actives
        $rules = $this->reminderRuleRepository->findActiveRules($companyId);

        if (empty($rules)) {
            $this->logger->info('No active reminder rules found');
            return $stats;
        }

        // Récupérer les factures en retard (envoyées mais non payées)
        $overdueInvoices = $this->getOverdueInvoices($companyId);

        $this->logger->info('Processing reminders', [
            'rules' => count($rules),
            'overdueInvoices' => count($overdueInvoices)
        ]);

        foreach ($overdueInvoices as $invoice) {
            $stats['checked']++;

            foreach ($rules as $rule) {
                if ($this->shouldSendReminder($invoice, $rule)) {
                    $this->dispatchReminder($invoice, $rule);
                    $stats['dispatched']++;
                    break; // Une seule relance par facture par exécution
                } else {
                    $stats['skipped']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Récupère les factures en retard de paiement
     *
     * @return Invoice[]
     */
    private function getOverdueInvoices(?string $companyId = null): array
    {
        $qb = $this->invoiceRepository->createQueryBuilder('i')
            ->leftJoin('i.client', 'c')
            ->addSelect('c')
            ->where('i.statut IN (:statuts)')
            ->andWhere('i.dateEcheance < :today')
            ->setParameter('statuts', [InvoiceStatus::ISSUED->value, InvoiceStatus::SENT->value])
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('i.dateEcheance', 'ASC');

        if ($companyId !== null) {
            $qb->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si une relance doit être envoyée pour une facture selon une règle
     */
    private function shouldSendReminder(Invoice $invoice, ReminderRule $rule): bool
    {
        // Vérifier que le client a un email
        if (!$invoice->getClient() || !$invoice->getClient()->getEmail()) {
            return false;
        }

        // Vérifier le nombre de relances déjà envoyées
        $reminderCount = $this->reminderRepository->countRemindersForInvoice($invoice);
        if ($reminderCount >= $rule->getMaxReminders()) {
            return false;
        }

        // Vérifier si cette règle a déjà été appliquée
        if ($this->reminderRepository->hasReminderBeenSent($invoice, $rule)) {
            return false;
        }

        // Calculer le nombre de jours depuis l'échéance
        $today = new \DateTime('today');
        $dueDate = $invoice->getDateEcheance();

        if (!$dueDate) {
            return false;
        }

        $daysOverdue = $today->diff($dueDate)->days;

        // Vérifier si le délai de la règle est atteint
        return $daysOverdue >= $rule->getDaysAfterDue();
    }

    /**
     * Dispatch un message de relance
     */
    private function dispatchReminder(Invoice $invoice, ReminderRule $rule): void
    {
        $message = new SendReminderMessage(
            $invoice->getId(),
            $rule->getId()
        );

        $this->messageBus->dispatch($message);

        $this->logger->info('Reminder dispatched', [
            'invoiceId' => $invoice->getId(),
            'invoiceNumero' => $invoice->getNumero(),
            'ruleName' => $rule->getName(),
            'ruleId' => $rule->getId()
        ]);
    }
}
