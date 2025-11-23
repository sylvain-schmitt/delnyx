<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Quote;
use App\Entity\Amendment;
use App\Entity\Invoice;
use App\Entity\CreditNote;
use App\Entity\EmailLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File as MimeFile;

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
        private readonly string $senderEmail = 'noreply@delnyx.com',
        private readonly string $senderName = 'Delnyx'
    ) {
    }

    /**
     * Envoie un devis par email
     */
    public function sendQuote(Quote $quote, ?string $customMessage = null): EmailLog
    {
        $client = $quote->getClient();
        
        $subject = sprintf('Devis %s - %s', $quote->getNumero(), $this->senderName);
        
        $html = $this->twig->render('emails/quote.html.twig', [
            'quote' => $quote,
            'client' => $client,
            'customMessage' => $customMessage,
            'subject' => $subject,
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
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }

    /**
     * Envoie une facture par email
     */
    public function sendInvoice(Invoice $invoice, ?string $customMessage = null): EmailLog
    {
        $client = $invoice->getClient();
        
        $subject = sprintf('Facture %s - %s', $invoice->getNumero(), $this->senderName);
        
        $html = $this->twig->render('emails/invoice.html.twig', [
            'invoice' => $invoice,
            'client' => $client,
            'customMessage' => $customMessage,
            'subject' => $subject,
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
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }

    /**
     * Envoie un avenant par email
     */
    public function sendAmendment(Amendment $amendment, ?string $customMessage = null): EmailLog
    {
        $quote = $amendment->getQuote();
        $client = $quote?->getClient();
        
        if (!$client) {
            throw new \RuntimeException('Impossible d\'envoyer l\'avenant : aucun client associé');
        }
        
        $subject = sprintf('Avenant %s - %s', $amendment->getNumero(), $this->senderName);
        
        $html = $this->twig->render('emails/amendment.html.twig', [
            'amendment' => $amendment,
            'quote' => $quote,
            'client' => $client,
            'customMessage' => $customMessage,
            'subject' => $subject,
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
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }

    /**
     * Envoie un avoir par email
     */
    public function sendCreditNote(CreditNote $creditNote, ?string $customMessage = null): EmailLog
    {
        $invoice = $creditNote->getInvoice();
        $client = $invoice?->getClient();
        
        if (!$client) {
            throw new \RuntimeException('Impossible d\'envoyer l\'avoir : aucun client associé');
        }
        
        $subject = sprintf('Avoir %s - %s', $creditNote->getNumber(), $this->senderName);
        
        $html = $this->twig->render('emails/credit_note.html.twig', [
            'creditNote' => $creditNote,
            'invoice' => $invoice,
            'client' => $client,
            'customMessage' => $customMessage,
            'subject' => $subject,
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
            pdfContent: $pdfContent,
            pdfFilename: $pdfFilename
        );
    }

    /**
     * Méthode générique d'envoi avec traçabilité
     */
    private function send(
        string $recipient,
        string $subject,
        string $html,
        string $entityType,
        int $entityId,
        string $type,
        ?string $pdfContent = null,
        ?string $pdfFilename = null
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
                ->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail))
                ->to($recipient)
                ->subject($subject)
                ->html($html);

            // Attacher le PDF si fourni
            if ($pdfContent && $pdfFilename) {
                $email->attach($pdfContent, $pdfFilename, 'application/pdf');
            }

            // Envoyer
            $this->mailer->send($email);
            
            $emailLog->setStatus('sent');
            $emailLog->setMetadata([
                'sent_successfully' => true,
                'sent_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'pdf_attached' => $pdfContent !== null
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
}


