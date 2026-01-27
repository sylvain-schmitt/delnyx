<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Repository\CompanySettingsRepository;

/**
 * Service de génération de PDF avec Dom PDF
 */
class PdfGeneratorService
{
    public function __construct(
        private Environment $twig,
        private KernelInterface $kernel,
        private CompanySettingsRepository $companySettingsRepository
    ) {}

    /**
     * Génère un PDF à partir d'un template Twig
     */
    public function generatePdf(string $template, array $data = []): Response
    {
        // Configuration DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);

        // Récupérer le logo (entreprise ou par défaut)
        $data['logo_base64'] = $this->getLogoBase64($data['company'] ?? null);

        // Rendu du template Twig
        $html = $this->twig->render($template, $data);

        // Chargement du HTML
        $dompdf->loadHtml($html);

        // Configuration du format
        $dompdf->setPaper('A4', 'portrait');

        // Rendu du PDF
        $dompdf->render();

        // Génération de la réponse
        $output = $dompdf->output();

        return new Response(
            $output,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . ($data['filename'] ?? 'document') . '.pdf"'
            ]
        );
    }

    /**
     * Génère et sauvegarde un PDF avec hash SHA256 pour archivage légal
     *
     * @return array ['response' => Response, 'filename' => string, 'hash' => string, 'path' => string]
     */
    public function generateAndSavePdf(string $template, array $data, string $storageDir = 'generated_pdfs'): array
    {
        // Configuration DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);

        // Récupérer le logo (entreprise ou par défaut)
        $data['logo_base64'] = $this->getLogoBase64($data['company'] ?? null);

        // Rendu du template Twig
        $html = $this->twig->render($template, $data);

        // Chargement et rendu du PDF
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Récupération du contenu PDF
        $pdfContent = $dompdf->output();

        // Calculer le hash SHA256 pour archivage légal (10 ans)
        $hash = hash('sha256', $pdfContent);

        // Créer le nom de fichier unique
        $filename = ($data['filename'] ?? 'document') . '_' . date('Ymd_His') . '.pdf';

        // Définir le chemin de stockage
        $storagePath = $this->kernel->getProjectDir() . '/var/' . $storageDir;

        // Créer le répertoire si nécessaire
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        // Sauvegarder le fichier
        $filePath = $storagePath . '/' . $filename;
        file_put_contents($filePath, $pdfContent);

        // Créer la réponse HTTP
        $response = new Response(
            $pdfContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . ($data['filename'] ?? 'document') . '.pdf"'
            ]
        );

        return [
            'response' => $response,
            'filename' => $filename,
            'hash' => $hash,
            'path' => $filePath
        ];
    }

    /**
     * Génère un PDF de devis
     *
     * @param mixed $devis Le devis (Quote entity)
     * @param bool $save Si true, sauvegarde le PDF et retourne un array avec hash
     * @return Response|array Response si $save=false, array si $save=true
     */
    public function generateDevisPdf($devis, bool $save = false): Response|array
    {
        // Récupérer CompanySetting
        $company = null;
        if ($devis->getCompanyId()) {
            $company = $this->companySettingsRepository->findByCompanyId($devis->getCompanyId());
        }

        $data = [
            'quote' => $devis,
            'company' => $company,
            'client' => $devis->getClient(),
            'filename' => 'devis-' . ($devis->getNumero() ?? $devis->getId())
        ];

        if ($save) {
            return $this->generateAndSavePdf('pdf/devis.html.twig', $data);
        }

        return $this->generatePdf('pdf/devis.html.twig', $data);
    }

    /**
     * Génère un PDF de facture
     *
     * @param mixed $facture La facture (Invoice entity)
     * @param bool $save Si true, sauvegarde le PDF et retourne un array avec hash
     * @return Response|array Response si $save=false, array si $save=true
     */
    public function generateFacturePdf($facture, bool $save = false): Response|array
    {
        // Récupérer CompanySetting
        $company = null;
        if ($facture->getCompanyId()) {
            $company = $this->companySettingsRepository->findByCompanyId($facture->getCompanyId());
        }

        // Déterminer le template selon le type de facture
        $isDepositInvoice = $facture->isDepositInvoice();
        $template = $isDepositInvoice ? 'pdf/facture_acompte.html.twig' : 'pdf/facture.html.twig';
        $filenamePrefix = $isDepositInvoice ? 'facture-acompte-' : 'facture-';

        $data = [
            'invoice' => $facture,
            'company' => $company,
            'client' => $facture->getClient(),
            'logo_base64' => $this->getLogoBase64($company),
            'filename' => $filenamePrefix . ($facture->getNumero() ?? $facture->getId())
        ];

        // Ajouter les données du deposit si facture d'acompte
        if ($isDepositInvoice && $facture->getSourceDeposit()) {
            $data['deposit'] = $facture->getSourceDeposit();
        }

        if ($save) {
            return $this->generateAndSavePdf($template, $data);
        }

        return $this->generatePdf($template, $data);
    }

    /**
     * Génère un PDF d'avenant
     *
     * @param mixed $avenant L'avenant (Amendment entity)
     * @param bool $save Si true, sauvegarde le PDF et retourne un array avec hash
     * @return Response|array Response si $save=false, array si $save=true
     */
    public function generateAvenantPdf($avenant, bool $save = false): Response|array
    {
        // Récupérer CompanySetting
        $company = null;
        if ($avenant->getCompanyId()) {
            $company = $this->companySettingsRepository->findByCompanyId($avenant->getCompanyId());
        }

        $data = [
            'amendment' => $avenant,
            'company' => $company,
            'quote' => $avenant->getQuote(),
            'client' => $avenant->getQuote()?->getClient(),
            'filename' => 'avenant-' . ($avenant->getNumero() ?? ('AV-' . ($avenant->getId() ?? 'document')))
        ];

        if ($save) {
            return $this->generateAndSavePdf('pdf/avenant.html.twig', $data);
        }

        return $this->generatePdf('pdf/avenant.html.twig', $data);
    }

    /**
     * Génère un PDF d'avoir
     *
     * @param mixed $creditNote L'avoir (CreditNote entity)
     * @param bool $save Si true, sauvegarde le PDF et retourne un array avec hash
     * @return Response|array Response si $save=false, array si $save=true
     */
    public function generateCreditNotePdf($creditNote, bool $save = false): Response|array
    {
        // Récupérer CompanySetting
        $company = null;
        if ($creditNote->getCompanyId()) {
            $company = $this->companySettingsRepository->findByCompanyId($creditNote->getCompanyId());
        }

        $data = [
            'credit_note' => $creditNote,
            'company' => $company,
            'invoice' => $creditNote->getInvoice(),
            'client' => $creditNote->getInvoice()?->getClient(),
            'filename' => $creditNote->getNumber() ?? ('avoir-' . $creditNote->getId())
        ];

        if ($save) {
            return $this->generateAndSavePdf('pdf/avoir.html.twig', $data);
        }

        return $this->generatePdf('pdf/avoir.html.twig', $data);
    }

    /**
     * Récupère le logo en base64 (entreprise ou par défaut)
     *
     * @param mixed $company CompanySettings ou null
     * @return string Logo en base64 ou chaîne vide
     */
    private function getLogoBase64($company): string
    {
        $logoPath = null;

        // Priorité au logo de l'entreprise s'il existe
        if ($company && method_exists($company, 'getLogoPath') && $company->getLogoPath()) {
            $logoPath = $this->kernel->getProjectDir() . '/public' . $company->getLogoPath();
        }

        // Fallback sur le logo par défaut
        if (!$logoPath || !file_exists($logoPath)) {
            $logoPath = $this->kernel->getProjectDir() . '/assets/images/logo-delnyx.svg';
        }

        if (!file_exists($logoPath)) {
            return '';
        }

        // Lire le fichier et déterminer le type MIME
        $logoData = file_get_contents($logoPath);
        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));

        // Déterminer le type MIME selon l'extension
        $mimeType = match ($extension) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/svg+xml', // Par défaut SVG
        };

        return 'data:' . $mimeType . ';base64,' . base64_encode($logoData);
    }
}
