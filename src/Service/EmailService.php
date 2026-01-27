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
use App\Entity\Deposit;
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
    public function sendPaymentConfirmation(Invoice $invoice, ?float $amount = null): EmailLog
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
            'amountPaid' => $amount,
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

    private ?MagicLinkService $magicLinkService = null;

    public function setMagicLinkService(MagicLinkService $magicLinkService): void
    {
        $this->magicLinkService = $magicLinkService;
    }

    /**
     * Envoie un email de demande d'accompte au client
     *
     * @param Deposit $deposit L'accompte pour lequel envoyer la demande
     * @param Quote|null $quote Le devis (optionnel, récupéré depuis le deposit si non fourni)
     * @param string|null $paymentUrl L'URL de paiement (optionnel, générée via MagicLink si non fournie)
     */
    public function sendDepositRequest(Deposit $deposit, ?Quote $quote = null, ?string $paymentUrl = null): EmailLog
    {
        $quote = $quote ?? $deposit->getQuote();
        $client = $quote->getClient();

        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Impossible d\'envoyer la demande d\'accompte : aucun email client');
        }

        // Générer l'URL de paiement si non fournie
        if ($paymentUrl === null) {
            if ($this->magicLinkService === null) {
                throw new \RuntimeException('MagicLinkService non configuré - impossible de générer l\'URL de paiement');
            }
            $paymentUrl = $this->magicLinkService->generateDepositPayLink($deposit);
        }

        $senderInfo = $this->getSenderInfo();
        $subject = sprintf('Demande d\'accompte - Devis %s', $quote->getNumero());

        $html = $this->twig->render('emails/deposit_request.html.twig', [
            'quote' => $quote,
            'deposit' => $deposit,
            'client' => $client,
            'subject' => $subject,
            'paymentUrl' => $paymentUrl,
            'companySettings' => $senderInfo['settings'],
        ]);

        // Attacher le PDF si disponible
        $pdfContent = null;
        $pdfFilename = null;
        $depositInvoice = $deposit->getDepositInvoice();
        if ($depositInvoice) {
            $pdfResponse = $this->pdfGeneratorService->generateFacturePdf($depositInvoice);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = 'facture-acompte-' . $depositInvoice->getNumero() . '.pdf';
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Deposit',
            entityId: $deposit->getId(),
            type: 'deposit_request',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }

    /**
     * Envoie un email de confirmation de paiement d'accompte
     * Inclut la facture d'acompte en pièce jointe si elle existe
     */
    public function sendDepositPaymentConfirmation(\App\Entity\Deposit $deposit): EmailLog
    {
        $quote = $deposit->getQuote();
        $client = $quote?->getClient();

        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Impossible d\'envoyer la confirmation : aucun email client');
        }

        $senderInfo = $this->getSenderInfo();
        $subject = sprintf('Confirmation de paiement - Accompte Devis %s', $quote->getNumero());

        $html = $this->twig->render('emails/deposit_payment_confirmation.html.twig', [
            'quote' => $quote,
            'deposit' => $deposit,
            'client' => $client,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        // Générer le PDF de la facture d'acompte si elle existe
        $pdfContent = null;
        $pdfFilename = null;
        $depositInvoice = $deposit->getDepositInvoice();

        if ($depositInvoice) {
            $pdfResponse = $this->pdfGeneratorService->generateFacturePdf($depositInvoice);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = 'facture-acompte-' . $depositInvoice->getNumero() . '.pdf';
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Deposit',
            entityId: $deposit->getId(),
            type: 'deposit_payment_confirmation',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }
    /**
     * Envoie une notification à l'admin pour un paiement manuel (Virement/Chèque)
     */
    public function sendManualPaymentNotification(Invoice $invoice): EmailLog
    {
        $senderInfo = $this->getSenderInfo();
        $adminEmail = $senderInfo['email']; // On envoie à l'email configuré dans les settings (email de l'entreprise)

        $subject = sprintf('Paiement manuel en attente - Facture %s', $invoice->getNumero());

        $html = $this->twig->render('emails/notification/manual_payment_admin.html.twig', [
            'invoice' => $invoice,
            'client' => $invoice->getClient(),
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        return $this->send(
            recipient: $adminEmail,
            subject: $subject,
            html: $html,
            entityType: 'Invoice',
            entityId: $invoice->getId(),
            type: 'manual_payment_notification',
            senderEmail: $senderInfo['email'], // Auto-envoi
            senderName: 'Delnyx System'
        );
    }

    /**
     * Envoie les instructions de paiement manuel (Virement/Chèque) au client
     */
    public function sendManualPaymentInstructions(Invoice $invoice): EmailLog
    {
        $client = $invoice->getClient();

        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Impossible d\'envoyer les instructions : aucun email client');
        }

        $senderInfo = $this->getSenderInfo();
        $subject = sprintf('Instructions de règlement - Facture %s', $invoice->getNumero());

        // Récupérer le nom du propriétaire
        $owner = $this->entityManager->getRepository(User::class)->findOneBy([]);
        $ownerName = $owner ? $owner->getNomComplet() : 'Sylvain Schmitt';

        $html = $this->twig->render('emails/manual_payment_instructions.html.twig', [
            'invoice' => $invoice,
            'client' => $client,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
            'ownerName' => $ownerName,
        ]);

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Invoice',
            entityId: $invoice->getId(),
            type: 'manual_payment_instructions',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name']
        );
    }
    /**
     * Envoie une notification générique à l'administrateur
     */
    public function sendAdminNotification(string $subject, string $message): void
    {
        $senderInfo = $this->getSenderInfo();
        $adminEmail = $senderInfo['email'];

        $email = (new Email())
            ->from(sprintf('%s <%s>', 'Delnyx System', $senderInfo['email']))
            ->to($adminEmail)
            ->subject('[NOTIF] ' . $subject)
            ->text($message)
            ->html(sprintf('<p>%s</p>', nl2br(htmlspecialchars($message))));

        $this->mailer->send($email);
    }

    /**
     * Envoie une notification à l'admin pour un paiement d'acompte manuel
     */
    public function sendManualDepositPaymentNotification(\App\Entity\Deposit $deposit): EmailLog
    {
        $senderInfo = $this->getSenderInfo();
        $adminEmail = $senderInfo['email'];

        $subject = sprintf('Paiement manuel acompte en attente - Devis %s', $deposit->getQuote()->getNumero());

        $html = $this->twig->render('emails/notification/manual_deposit_payment_admin.html.twig', [
            'deposit' => $deposit,
            'quote' => $deposit->getQuote(),
            'client' => $deposit->getQuote()->getClient(),
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        return $this->send(
            recipient: $adminEmail,
            subject: $subject,
            html: $html,
            entityType: 'Quote',
            entityId: $deposit->getQuote()->getId(),
            type: 'manual_deposit_payment_notification',
            senderEmail: $senderInfo['email'],
            senderName: 'Delnyx System'
        );
    }

    /**
     * Envoie les instructions de paiement manuel pour un acompte
     */
    public function sendManualDepositPaymentInstructions(\App\Entity\Deposit $deposit): EmailLog
    {
        $quote = $deposit->getQuote();
        $client = $quote->getClient();

        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Impossible d\'envoyer les instructions : aucun email client');
        }

        $senderInfo = $this->getSenderInfo();
        $subject = sprintf('Instructions de règlement acompte - Devis %s', $quote->getNumero());

        $owner = $this->entityManager->getRepository(User::class)->findOneBy([]);
        $ownerName = $owner ? $owner->getNomComplet() : 'Sylvain Schmitt';

        $html = $this->twig->render('emails/manual_deposit_payment_instructions.html.twig', [
            'deposit' => $deposit,
            'quote' => $quote,
            'client' => $client,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
            'ownerName' => $ownerName,
        ]);

        // Attacher le PDF si disponible (normalement déjà généré)
        $pdfContent = null;
        $pdfFilename = null;
        $depositInvoice = $deposit->getDepositInvoice();
        if ($depositInvoice) {
            $pdfResponse = $this->pdfGeneratorService->generateFacturePdf($depositInvoice);
            $pdfContent = $pdfResponse->getContent();
            $pdfFilename = 'facture-acompte-' . $depositInvoice->getNumero() . '.pdf';
        }

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Quote',
            entityId: $quote->getId(),
            type: 'manual_deposit_payment_instructions',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name'],
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }

    /**
     * Envoie une notification d'échec de paiement au client
     */
    public function sendPaymentFailed(Invoice $invoice, string $reason, string $actionUrl): EmailLog
    {
        $client = $invoice->getClient();

        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Impossible d\'envoyer la notification d\'échec : aucun email client');
        }

        $senderInfo = $this->getSenderInfo();
        $subject = sprintf('Échec du paiement - Facture %s', $invoice->getNumero());

        $html = $this->twig->render('emails/payment_failed.html.twig', [
            'invoice' => $invoice,
            'client' => $client,
            'reason' => $reason,
            'actionUrl' => $actionUrl,
            'subject' => $subject,
            'companySettings' => $senderInfo['settings'],
        ]);

        return $this->send(
            recipient: $client->getEmail(),
            subject: $subject,
            html: $html,
            entityType: 'Invoice',
            entityId: $invoice->getId(),
            type: 'payment_failed',
            senderEmail: $senderInfo['email'],
            senderName: $senderInfo['name']
        );
    }
}
