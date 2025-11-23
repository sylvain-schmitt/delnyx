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

        // Conversion du logo SVG en base64 (meilleure qualité)
        $logoPath = $this->kernel->getProjectDir() . '/assets/images/logo-delnyx.svg';
        $logoBase64 = '';

        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/svg+xml;base64,' . base64_encode($logoData);
        }

        $data['logo_base64'] = $logoBase64;

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

        // Conversion du logo SVG en base64
        $logoPath = $this->kernel->getProjectDir() . '/assets/images/logo-delnyx.svg';
        $logoBase64 = '';

        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/svg+xml;base64,' . base64_encode($logoData);
        }

        $data['logo_base64'] = $logoBase64;

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

        $data = [
            'invoice' => $facture,
            'company' => $company,
            'client' => $facture->getClient(),
            'filename' => 'facture-' . ($facture->getNumero() ?? $facture->getId())
        ];

        if ($save) {
            return $this->generateAndSavePdf('pdf/facture.html.twig', $data);
        }

        return $this->generatePdf('pdf/facture.html.twig', $data);
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
}
