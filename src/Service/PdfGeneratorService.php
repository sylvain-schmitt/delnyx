<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service de génération de PDF avec DomPDF
 */
class PdfGeneratorService
{
    public function __construct(
        private Environment $twig
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
}
