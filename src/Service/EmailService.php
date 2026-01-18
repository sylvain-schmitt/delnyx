<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Quote;
use App\Entity\Amendment;
use App\Entity\Invoice;
use App\Entity\CreditNote;
use App\Entity\EmailLog;
use App\Entity\User;
use App\Entity\CompanySettings;
use App\Repository\CompanySettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;

/**
 * Service d'envoi d'emails pour les documents
 *
 * Gère l'envoi et la traçabilité de tous les emails
 */
class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly PdfGeneratorService $pdfGeneratorService,
        private readonly CompanySettingsRepository $companySettingsRepository,
    ) {}

    /**
     * Récupère les informations d'expéditeur depuis les paramètres de l'entreprise
     *
     * @return array{email: string, name: string, settings: ?CompanySettings}
     */
    private function getSenderInfo(): array
    {
        $settings = $this->companySettingsRepository->findOneBy([]);

        if ($settings) {
            return [
                'email' => $settings->getEmail() ?: 'contact@delnyx.com',
                'name' => $settings->getRaisonSociale() ?: 'Delnyx',
                'settings' => $settings
            ];
        }

        // Fallback si pas de settings configurés
        return [
            'email' => 'contact@delnyx.com',
            'name' => 'Delnyx',
            'settings' => null
        ];
    }

    /**
     * Envoie un devis par email
     *
     * @param array $additionalAttachments Fichiers supplémentaires à joindre (UploadedFile[])
     */
    public function sendQuote(Quote $quote, ?string $customMessage = null, array $additionalAttachments = []): EmailLog
    {
        $client = $quote->getClient();
        $senderInfo = $this->getSenderInfo();

        $subject = sprintf('Devis %s - %s', $quote->getNumero(), $senderInfo['name']);

        $html = $this->twig->render('emails/quote.html.twig', [
            'quote' => $quote,
            'client' => $client,
            'customMessage' => $customMessage,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        // Générer le PDF
        $pdfContent = null;
        $pdfFilename = null;
        try {
            $pdfResponse = $this->pdfGeneratorService->generateDevisPdf($quote, false);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = sprintf('devis-%s.pdf', $quote->getNumero() ?? $quote->getId());
        } catch (\Exception $e) {
            // Si la génération PDF échoue, on envoie quand même l'email sans PDF
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Quote',
            entityId: $quote->getId(),
            type: 'quote',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename,
            additionalAttachments: $additionalAttachments
        );
    }

    /**
     * Envoie une facture par email
     *
     * @param array $additionalAttachments Fichiers supplémentaires à joindre (UploadedFile[])
     */
    public function sendInvoice(Invoice $invoice, ?string $customMessage = null, array $additionalAttachments = []): EmailLog
    {
        $client = $invoice->getClient();
        $senderInfo = $this->getSenderInfo();

        $subject = sprintf('Facture %s - %s', $invoice->getNumero(), $senderInfo['name']);

        $html = $this->twig->render('emails/invoice.html.twig', [
            'invoice' => $invoice,
            'client' => $client,
            'customMessage' => $customMessage,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        // Générer le PDF
        $pdfContent = null;
        $pdfFilename = null;
        try {
            $pdfResponse = $this->pdfGeneratorService->generateFacturePdf($invoice, false);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = sprintf('facture-%s.pdf', $invoice->getNumero() ?? $invoice->getId());
        } catch (\Exception $e) {
            // Si la génération PDF échoue, on envoie quand même l'email sans PDF
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Invoice',
            entityId: $invoice->getId(),
            type: 'invoice',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename,
            additionalAttachments: $additionalAttachments
        );
    }

    /**
     * Envoie un avenant par email
     *
     * @param array $additionalAttachments Fichiers supplémentaires à joindre (UploadedFile[])
     */
    public function sendAmendment(Amendment $amendment, ?string $customMessage = null, array $additionalAttachments = []): EmailLog
    {
        $quote = $amendment->getQuote();
        $client = $quote?->getClient();

        if (!$client) {
            throw new \RuntimeException('Impossible d\'envoyer l\'avenant : aucun client associé');
        }

        $senderInfo = $this->getSenderInfo();
        $subject = sprintf('Avenant %s - %s', $amendment->getNumero(), $senderInfo['name']);

        $html = $this->twig->render('emails/amendment.html.twig', [
            'amendment' => $amendment,
            'quote' => $quote,
            'client' => $client,
            'customMessage' => $customMessage,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        // Générer le PDF
        $pdfContent = null;
        $pdfFilename = null;
        try {
            $pdfResponse = $this->pdfGeneratorService->generateAvenantPdf($amendment, false);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = sprintf('avenant-%s.pdf', $amendment->getNumero() ?? $amendment->getId());
        } catch (\Exception $e) {
            // Si la génération PDF échoue, on envoie quand même l'email sans PDF
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Amendment',
            entityId: $amendment->getId(),
            type: 'amendment',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename,
            additionalAttachments: $additionalAttachments
        );
    }

    /**
     * Envoie un avoir par email
     *
     * @param array $additionalAttachments Fichiers supplémentaires à joindre (UploadedFile[])
     */
    public function sendCreditNote(CreditNote $creditNote, ?string $customMessage = null, array $additionalAttachments = []): EmailLog
    {
        $invoice = $creditNote->getInvoice();
        $client = $invoice?->getClient();

        if (!$client) {
            throw new \RuntimeException('Impossible d\'envoyer l\'avoir : aucun client associé');
        }

        $senderInfo = $this->getSenderInfo();
        $subject = sprintf('Avoir %s - %s', $creditNote->getNumber(), $senderInfo['name']);

        $html = $this->twig->render('emails/credit_note.html.twig', [
            'creditNote' => $creditNote,
            'invoice' => $invoice,
            'client' => $client,
            'customMessage' => $customMessage,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        // Générer le PDF
        $pdfContent = null;
        $pdfFilename = null;
        try {
            $pdfResponse = $this->pdfGeneratorService->generateCreditNotePdf($creditNote, false);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = sprintf('avoir-%s.pdf', $creditNote->getNumber() ?? $creditNote->getId());
        } catch (\Exception $e) {
            // Si la génération PDF échoue, on envoie quand même l'email sans PDF
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'CreditNote',
            entityId: $creditNote->getId(),
            type: 'credit_note',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename,
            additionalAttachments: $additionalAttachments
        );
    }

    /**
     * Méthode générique d'envoi avec traçabilité
     *
     * @param array $additionalAttachments Fichiers supplémentaires à joindre (UploadedFile[])
     */
    private function send(
        string $recipient,
        string $subject,
        string $html,
        string $entityType,
        int $entityId,
        string $type,
        string $senderEmail,
        string $senderName,
        ?string $pdfContent = null,
        ?string $pdfFilename = null,
        array $additionalAttachments = []
    ): EmailLog {
        // Créer le log avant l'envoi
        $emailLog = new EmailLog();
        $emailLog->setEntityType($entityType);
        $emailLog->setEntityId($entityId);
        $emailLog->setRecipient($recipient);
        $emailLog->setSubject($subject);
        $emailLog->setType($type);

        // Enregistrer l'utilisateur qui envoie
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $emailLog->setUserId($user->getId());
            $emailLog->setUserEmail($user->getEmail());
        }

        try {
            // Créer l'email
            $email = (new Email())
                ->from(sprintf('%s <%s>', $senderName, $senderEmail))
                ->to($recipient)
                ->subject($subject)
                ->html($html);

            // Attacher le PDF si fourni
            if ($pdfContent && $pdfFilename) {
                $email->attach($pdfContent, $pdfFilename, 'application/pdf');
            }

            // Attacher les fichiers supplémentaires
            $attachedFilesInfo = [];
            foreach ($additionalAttachments as $file) {
                if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $file->isValid()) {
                    $email->attachFromPath(
                        $file->getPathname(),
                        $file->getClientOriginalName(),
                        $file->getMimeType()
                    );
                    $attachedFilesInfo[] = [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType()
                    ];
                }
            }

            // Forcer le transport "documents"
            $email->getHeaders()->addTextHeader('X-Transport', 'documents');

            // Envoyer
            $this->mailer->send($email);

            $emailLog->setStatus('sent');
            $emailLog->setMetadata([
                'sent_successfully' => true,
                'sent_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'pdf_attached' => $pdfContent !== null,
                'additional_attachments_count' => count($attachedFilesInfo),
                'additional_attachments' => $attachedFilesInfo
            ]);
        } catch (\Exception $e) {
            $emailLog->setStatus('failed');
            $emailLog->setErrorMessage($e->getMessage());
            $emailLog->setMetadata([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Persister le log
        $this->entityManager->persist($emailLog);
        $this->entityManager->flush();

        return $emailLog;
    }

    /**
     * Récupère l'historique des emails pour une entité
     */
    public function getEmailHistory(string $entityType, int $entityId): array
    {
        return $this->entityManager
            ->getRepository(EmailLog::class)
            ->findByEntity($entityType, $entityId);
    }

    /**
     * Envoie un email de relance pour une facture
     */
    public function sendReminderEmail(
        Invoice $invoice,
        string $subject,
        string $htmlContent,
        ?CompanySettings $companySettings = null
    ): EmailLog {
        $client = $invoice->getClient();

        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Impossible d\'envoyer la relance : aucun email client');
        }

        $senderInfo = $this->getSenderInfo();

        // Encapsuler le contenu dans un template email
        $html = $this->twig->render('emails/reminder.html.twig', [
            'invoice' => $invoice,
            'client' => $client,
            'content' => $htmlContent,
            'subject' => $subject,
            'companySettings' => $companySettings ?? $senderInfo['settings'],
        ]);

        // Générer le PDF de la facture
        $pdfContent = null;
        $pdfFilename = null;
        try {
            $pdfResponse = $this->pdfGeneratorService->generateFacturePdf($invoice, false);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = sprintf('facture-%s.pdf', $invoice->getNumero() ?? $invoice->getId());
        } catch (\Exception $e) {
            // Si la génération PDF échoue, on envoie quand même l'email sans PDF
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Invoice',
            entityId: $invoice->getId(),
            type: 'reminder',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }

    /**
     * Envoie un email de confirmation de paiement
     */
    public function sendPaymentConfirmation(Invoice $invoice): EmailLog
    {
        $client = $invoice->getClient();

        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Impossible d\'envoyer la confirmation : aucun email client');
        }

        $senderInfo = $this->getSenderInfo();
        $subject = sprintf('Confirmation de paiement - Facture %s', $invoice->getNumero());

        $html = $this->twig->render('emails/payment_confirmation.html.twig', [
            'invoice' => $invoice,
            'client' => $client,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        // Générer le PDF de la facture
        $pdfContent = null;
        $pdfFilename = null;
        try {
            $pdfResponse = $this->pdfGeneratorService->generateFacturePdf($invoice, false);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = sprintf('facture-%s.pdf', $invoice->getNumero() ?? $invoice->getId());
        } catch (\Exception $e) {
            // Si la génération PDF échoue, on envoie quand même l'email sans PDF
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Invoice',
            entityId: $invoice->getId(),
            type: 'payment_confirmation',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }
}
