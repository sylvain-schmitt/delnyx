<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Quote;
use App\Entity\Invoice;
use App\Entity\Amendment;
use App\Entity\CreditNote;
use App\Entity\CompanySettings;
use App\Entity\Client;
use App\Entity\QuoteStatus;
use App\Entity\InvoiceStatus;
use App\Entity\AmendmentStatus;
use App\Entity\CreditNoteStatus;
use App\Repository\QuoteRepository;
use App\Repository\InvoiceRepository;
use App\Repository\AmendmentRepository;
use App\Repository\CreditNoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service pour régénérer les PDF lorsque les informations de l'émetteur ou du client changent
 * 
 * Supprime automatiquement les anciens PDF pour économiser l'espace disque
 */
class PdfRegenerationService
{
    public function __construct(
        private readonly PdfGeneratorService $pdfGeneratorService,
        private readonly QuoteRepository $quoteRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly AmendmentRepository $amendmentRepository,
        private readonly CreditNoteRepository $creditNoteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Régénère tous les PDF pour une entreprise donnée
     * 
     * @param string $companyId L'ID de l'entreprise
     * @return array Statistiques de régénération
     */
    public function regenerateForCompany(string $companyId): array
    {
        $stats = [
            'quotes' => 0,
            'invoices' => 0,
            'amendments' => 0,
            'credit_notes' => 0,
            'errors' => 0,
        ];

        // Régénérer les devis signés/envoyés (pas de statut ISSUED pour les quotes)
        $quotes = $this->quoteRepository->createQueryBuilder('q')
            ->where('q.companyId = :companyId')
            ->andWhere('q.statut IN (:statuses)')
            ->andWhere('q.pdfFilename IS NOT NULL')
            ->setParameter('companyId', $companyId)
            ->setParameter('statuses', [
                QuoteStatus::SIGNED->value,
                QuoteStatus::SENT->value,
            ])
            ->getQuery()
            ->getResult();

        foreach ($quotes as $quote) {
            try {
                $this->regenerateQuotePdf($quote);
                $stats['quotes']++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la régénération du PDF du devis', [
                    'quote_id' => $quote->getId(),
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        // Régénérer les factures émises/envoyées
        $invoices = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.companyId = :companyId')
            ->andWhere('i.statut IN (:statuses)')
            ->andWhere('i.pdfFilename IS NOT NULL')
            ->setParameter('companyId', $companyId)
            ->setParameter('statuses', [
                InvoiceStatus::ISSUED->value,
                InvoiceStatus::SENT->value,
                InvoiceStatus::PAID->value,
            ])
            ->getQuery()
            ->getResult();

        foreach ($invoices as $invoice) {
            try {
                $this->regenerateInvoicePdf($invoice);
                $stats['invoices']++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la régénération du PDF de la facture', [
                    'invoice_id' => $invoice->getId(),
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        // Régénérer les avenants signés/envoyés
        $amendments = $this->amendmentRepository->createQueryBuilder('a')
            ->where('a.companyId = :companyId')
            ->andWhere('a.statut IN (:statuses)')
            ->andWhere('a.pdfFilename IS NOT NULL')
            ->setParameter('companyId', $companyId)
            ->setParameter('statuses', [
                AmendmentStatus::SIGNED->value,
                AmendmentStatus::SENT->value,
            ])
            ->getQuery()
            ->getResult();

        foreach ($amendments as $amendment) {
            try {
                $this->regenerateAmendmentPdf($amendment);
                $stats['amendments']++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la régénération du PDF de l\'avenant', [
                    'amendment_id' => $amendment->getId(),
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        // Régénérer les avoirs émis/envoyés
        $creditNotes = $this->creditNoteRepository->createQueryBuilder('cn')
            ->where('cn.companyId = :companyId')
            ->andWhere('cn.statut IN (:statuses)')
            ->andWhere('cn.pdfFilename IS NOT NULL')
            ->setParameter('companyId', $companyId)
            ->setParameter('statuses', [
                CreditNoteStatus::ISSUED->value,
                CreditNoteStatus::SENT->value,
                CreditNoteStatus::REFUNDED->value,
            ])
            ->getQuery()
            ->getResult();

        foreach ($creditNotes as $creditNote) {
            try {
                $this->regenerateCreditNotePdf($creditNote);
                $stats['credit_notes']++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la régénération du PDF de l\'avoir', [
                    'credit_note_id' => $creditNote->getId(),
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Régénération PDF pour entreprise', [
            'company_id' => $companyId,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Régénère tous les PDF pour un client donné
     * 
     * @param Client $client Le client
     * @return array Statistiques de régénération
     */
    public function regenerateForClient(Client $client): array
    {
        $stats = [
            'quotes' => 0,
            'invoices' => 0,
            'amendments' => 0,
            'credit_notes' => 0,
            'errors' => 0,
        ];

        // Régénérer les devis signés/envoyés du client (pas de statut ISSUED pour les quotes)
        $quotes = $this->quoteRepository->createQueryBuilder('q')
            ->where('q.client = :client')
            ->andWhere('q.statut IN (:statuses)')
            ->andWhere('q.pdfFilename IS NOT NULL')
            ->setParameter('client', $client)
            ->setParameter('statuses', [
                QuoteStatus::SIGNED->value,
                QuoteStatus::SENT->value,
            ])
            ->getQuery()
            ->getResult();

        foreach ($quotes as $quote) {
            try {
                $this->regenerateQuotePdf($quote);
                $stats['quotes']++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la régénération du PDF du devis', [
                    'quote_id' => $quote->getId(),
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        // Régénérer les factures émises/envoyées du client
        $invoices = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.client = :client')
            ->andWhere('i.statut IN (:statuses)')
            ->andWhere('i.pdfFilename IS NOT NULL')
            ->setParameter('client', $client)
            ->setParameter('statuses', [
                InvoiceStatus::ISSUED->value,
                InvoiceStatus::SENT->value,
                InvoiceStatus::PAID->value,
            ])
            ->getQuery()
            ->getResult();

        foreach ($invoices as $invoice) {
            try {
                $this->regenerateInvoicePdf($invoice);
                $stats['invoices']++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la régénération du PDF de la facture', [
                    'invoice_id' => $invoice->getId(),
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        // Régénérer les avenants signés/envoyés du client
        $amendments = $this->amendmentRepository->createQueryBuilder('a')
            ->join('a.quote', 'q')
            ->where('q.client = :client')
            ->andWhere('a.statut IN (:statuses)')
            ->andWhere('a.pdfFilename IS NOT NULL')
            ->setParameter('client', $client)
            ->setParameter('statuses', [
                AmendmentStatus::SIGNED->value,
                AmendmentStatus::SENT->value,
            ])
            ->getQuery()
            ->getResult();

        foreach ($amendments as $amendment) {
            try {
                $this->regenerateAmendmentPdf($amendment);
                $stats['amendments']++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la régénération du PDF de l\'avenant', [
                    'amendment_id' => $amendment->getId(),
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        // Régénérer les avoirs émis/envoyés du client
        $creditNotes = $this->creditNoteRepository->createQueryBuilder('cn')
            ->join('cn.invoice', 'i')
            ->where('i.client = :client')
            ->andWhere('cn.statut IN (:statuses)')
            ->andWhere('cn.pdfFilename IS NOT NULL')
            ->setParameter('client', $client)
            ->setParameter('statuses', [
                CreditNoteStatus::ISSUED->value,
                CreditNoteStatus::SENT->value,
                CreditNoteStatus::REFUNDED->value,
            ])
            ->getQuery()
            ->getResult();

        foreach ($creditNotes as $creditNote) {
            try {
                $this->regenerateCreditNotePdf($creditNote);
                $stats['credit_notes']++;
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la régénération du PDF de l\'avoir', [
                    'credit_note_id' => $creditNote->getId(),
                    'error' => $e->getMessage(),
                ]);
                $stats['errors']++;
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Régénération PDF pour client', [
            'client_id' => $client->getId(),
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * Régénère le PDF d'un devis
     */
    private function regenerateQuotePdf(Quote $quote): void
    {
        $oldFilename = $quote->getPdfFilename();
        
        // Générer le nouveau PDF
        $pdfResult = $this->pdfGeneratorService->generateDevisPdf($quote, true);
        
        // Supprimer l'ancien PDF
        if ($oldFilename) {
            $this->deletePdfFile('var/generated_pdfs', $oldFilename);
        }
        
        // Mettre à jour l'entité
        $quote->setPdfFilename($pdfResult['filename']);
        $quote->setPdfHash($pdfResult['hash']);
    }

    /**
     * Régénère le PDF d'une facture
     */
    private function regenerateInvoicePdf(Invoice $invoice): void
    {
        $oldFilename = $invoice->getPdfFilename();
        
        // Générer le nouveau PDF
        $pdfResult = $this->pdfGeneratorService->generateFacturePdf($invoice, true);
        
        // Supprimer l'ancien PDF
        if ($oldFilename) {
            $this->deletePdfFile('var/generated_pdfs', $oldFilename);
        }
        
        // Mettre à jour l'entité
        $invoice->setPdfFilename($pdfResult['filename']);
        $invoice->setPdfHash($pdfResult['hash']);
    }

    /**
     * Régénère le PDF d'un avenant
     */
    private function regenerateAmendmentPdf(Amendment $amendment): void
    {
        $oldFilename = $amendment->getPdfFilename();
        
        // Générer le nouveau PDF
        $pdfResult = $this->pdfGeneratorService->generateAvenantPdf($amendment, true);
        
        // Supprimer l'ancien PDF
        if ($oldFilename) {
            $this->deletePdfFile('var/generated_pdfs', $oldFilename);
        }
        
        // Mettre à jour l'entité
        $amendment->setPdfFilename($pdfResult['filename']);
        $amendment->setPdfHash($pdfResult['hash']);
    }

    /**
     * Régénère le PDF d'un avoir
     */
    private function regenerateCreditNotePdf(CreditNote $creditNote): void
    {
        $oldFilename = $creditNote->getPdfFilename();
        
        // Générer le nouveau PDF (les avoirs utilisent une méthode différente)
        $response = $this->pdfGeneratorService->generateCreditNotePdf($creditNote, false);
        $pdfContent = $response->getContent();
        
        // Calculer le hash SHA256
        $hash = hash('sha256', $pdfContent);
        
        // Supprimer l'ancien PDF
        if ($oldFilename) {
            $this->deletePdfFile('public/uploads/credit_notes', $oldFilename);
        }
        
        // Sauvegarder le nouveau fichier
        $filename = sprintf('avoir-%s-%s.pdf', $creditNote->getNumber(), uniqid());
        $uploadDir = $this->params->get('kernel.project_dir') . '/public/uploads/credit_notes';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        file_put_contents($uploadDir . '/' . $filename, $pdfContent);
        
        // Mettre à jour l'entité
        $creditNote->setPdfFilename($filename);
        $creditNote->setPdfHash($hash);
    }

    /**
     * Supprime un fichier PDF
     */
    private function deletePdfFile(string $directory, string $filename): void
    {
        $projectDir = $this->params->get('kernel.project_dir');
        $filePath = $projectDir . '/' . $directory . '/' . $filename;
        
        if (file_exists($filePath)) {
            unlink($filePath);
            $this->logger->debug('PDF supprimé', [
                'path' => $filePath,
            ]);
        }
    }
}

