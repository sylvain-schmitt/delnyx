<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Service de génération de PDF avec DomPDF
 */
class PdfGeneratorService
{
    public function __construct(
        private Environment $twig,
        private KernelInterface $kernel
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
                'Content-Disposition' => 'inline; filename="' . $data['filename'] ?? 'document' . '.pdf"'
            ]
        );
    }

    /**
     * Génère un PDF de devis
     */
    public function generateDevisPdf($devis): Response
    {
        return $this->generatePdf('pdf/devis.html.twig', [
            'devis' => $devis,
            'filename' => 'devis-' . $devis->getNumero()
        ]);
    }

    /**
     * Génère un PDF de facture
     */
    public function generateFacturePdf($facture): Response
    {
        return $this->generatePdf('pdf/facture.html.twig', [
            'facture' => $facture,
            'filename' => 'facture-' . $facture->getNumero()
        ]);
    }

    /**
     * Génère un PDF d'avenant
     */
    public function generateAvenantPdf($avenant): Response
    {
        return $this->generatePdf('pdf/avenant.html.twig', [
            'avenant' => $avenant,
            'filename' => 'avenant-' . ($avenant->getNumero() ?? ('AV-' . ($avenant->getId() ?? 'document')))
        ]);
    }
}
