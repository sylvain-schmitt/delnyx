<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reminder;
use App\Message\SendReminderMessage;
use App\Repository\InvoiceRepository;
use App\Repository\ReminderRepository;
use App\Repository\ReminderRuleRepository;
use App\Repository\CompanySettingsRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendReminderHandler
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private ReminderRuleRepository $reminderRuleRepository,
        private ReminderRepository $reminderRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private EmailService $emailService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function __invoke(SendReminderMessage $message): void
    {
        $invoice = $this->invoiceRepository->find($message->getInvoiceId());
        $rule = $this->reminderRuleRepository->find($message->getRuleId());

        if (!$invoice || !$rule) {
            $this->logger->warning('SendReminderHandler: Invoice or Rule not found', [
                'invoiceId' => $message->getInvoiceId(),
                'ruleId' => $message->getRuleId()
            ]);
            return;
        }

        // Créer l'entrée de relance
        $reminder = new Reminder();
        $reminder->setInvoice($invoice);
        $reminder->setRule($rule);

        // Récupérer le client et vérifier l'email
        $client = $invoice->getClient();
        if (!$client || !$client->getEmail()) {
            $reminder->markAsSkipped('Client sans adresse email');
            $this->entityManager->persist($reminder);
            $this->entityManager->flush();
            return;
        }

        // Récupérer les paramètres de l'entreprise
        $companySettings = null;
        if ($invoice->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($invoice->getCompanyId());
        }
        if (!$companySettings) {
            $companySettings = $this->companySettingsRepository->findOneBy([]);
        }

        // Préparer le sujet de l'email (généré automatiquement par la règle)
        $subject = $rule->getEmailSubject($invoice);

        // Le contenu HTML est généré par le template Twig dans EmailService
        $htmlContent = '';

        $reminder->setEmailTo($client->getEmail());
        $reminder->setEmailSubject($subject);

        try {
            // Envoyer l'email via EmailService
            $emailLog = $this->emailService->sendReminderEmail(
                $invoice,
                $subject,
                $htmlContent,
                $companySettings
            );

            if ($emailLog && $emailLog->getStatus() === 'sent') {
                $reminder->markAsSent();
                $this->logger->info('Reminder sent successfully', [
                    'invoiceId' => $invoice->getId(),
                    'invoiceNumero' => $invoice->getNumero(),
                    'ruleName' => $rule->getName()
                ]);
            } else {
                $errorMessage = $emailLog?->getErrorMessage() ?? 'Erreur inconnue';
                $reminder->markAsFailed($errorMessage);
                $this->logger->error('Reminder failed to send', [
                    'invoiceId' => $invoice->getId(),
                    'error' => $errorMessage
                ]);
            }
        } catch (\Exception $e) {
            $reminder->markAsFailed($e->getMessage());
            $this->logger->error('Reminder exception', [
                'invoiceId' => $invoice->getId(),
                'exception' => $e->getMessage()
            ]);
        }

        $this->entityManager->persist($reminder);
        $this->entityManager->flush();
    }
}
