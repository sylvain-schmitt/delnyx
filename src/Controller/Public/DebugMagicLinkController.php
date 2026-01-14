<?php

namespace App\Controller\Public;

use App\Repository\QuoteRepository;
use App\Service\MagicLinkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DebugMagicLinkController extends AbstractController
{
    public function __construct(
        private MagicLinkService $magicLinkService,
        private QuoteRepository $quoteRepository,
        private string $appSecret,
    ) {
    }

    #[Route('/debug/magic-link/{id}', name: 'debug_magic_link')]
    public function debug(int $id): Response
    {
        $quote = $this->quoteRepository->find($id);
        
        if (!$quote) {
            return new Response("Devis #$id introuvable", 404);
        }

        // Générer un lien
        $viewLink = $this->magicLinkService->generatePublicLink($quote, 'view', 30);
        $signLink = $this->magicLinkService->generatePublicLink($quote, 'sign', 30);
        
        // Parser les liens
        $viewParsed = parse_url($viewLink);
        parse_str($viewParsed['query'] ?? '', $viewParams);
        
        $signParsed = parse_url($signLink);
        parse_str($signParsed['query'] ?? '', $signParams);

        // Tester la vérification
        $viewValid = $this->magicLinkService->verifySignature(
            'quote',
            $id,
            'view',
            (int)($viewParams['expires'] ?? 0),
            $viewParams['signature'] ?? ''
        );

        $signValid = $this->magicLinkService->verifySignature(
            'quote',
            $id,
            'sign',
            (int)($signParams['expires'] ?? 0),
            $signParams['signature'] ?? ''
        );

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Debug Magic Links - Devis #{$id}</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e293b; color: #e2e8f0; }
        .section { background: #334155; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        a { color: #60a5fa; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 8px; text-align: left; border-bottom: 1px solid #475569; }
        th { color: #94a3b8; }
    </style>
</head>
<body>
    <h1>🔍 Debug Magic Links - Devis #{$id}</h1>
    
    <div class="section">
        <h2>Configuration</h2>
        <table>
            <tr>
                <th>Paramètre</th>
                <th>Valeur</th>
            </tr>
            <tr>
                <td>APP_SECRET configuré</td>
                <td class="
HTML;

        $secretStatus = empty($this->appSecret) ? '<span class="error">❌ VIDE</span>' : '<span class="success">✅ Présent</span>';
        
        $html .= $secretStatus . '</td></tr>';
        $html .= '<tr><td>Longueur du secret</td><td>' . strlen($this->appSecret) . ' caractères</td></tr>';
        $html .= '<tr><td>Secret (10 premiers car)</td><td>' . htmlspecialchars(substr($this->appSecret, 0, 10)) . '...</td></tr>';
        $html .= '</table></div>';

        $html .= '<div class="section"><h2>Lien VIEW</h2>';
        $html .= '<p><strong>URL complète :</strong><br><a href="' . htmlspecialchars($viewLink) . '" target="_blank">' . htmlspecialchars($viewLink) . '</a></p>';
        $html .= '<table>';
        $html .= '<tr><td>Expires</td><td>' . ($viewParams['expires'] ?? 'N/A') . ' (' . date('Y-m-d H:i:s', $viewParams['expires'] ?? 0) . ')</td></tr>';
        $html .= '<tr><td>Signature</td><td>' . htmlspecialchars($viewParams['signature'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td>Vérification</td><td class="' . ($viewValid ? 'success' : 'error') . '">' . ($viewValid ? '✅ VALIDE' : '❌ INVALIDE') . '</td></tr>';
        $html .= '</table></div>';

        $html .= '<div class="section"><h2>Lien SIGN</h2>';
        $html .= '<p><strong>URL complète :</strong><br><a href="' . htmlspecialchars($signLink) . '" target="_blank">' . htmlspecialchars($signLink) . '</a></p>';
        $html .= '<table>';
        $html .= '<tr><td>Expires</td><td>' . ($signParams['expires'] ?? 'N/A') . ' (' . date('Y-m-d H:i:s', $signParams['expires'] ?? 0) . ')</td></tr>';
        $html .= '<tr><td>Signature</td><td>' . htmlspecialchars($signParams['signature'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><td>Vérification</td><td class="' . ($signValid ? 'success' : 'error') . '">' . ($signValid ? '✅ VALIDE' : '❌ INVALIDE') . '</td></tr>';
        $html .= '</table></div>';

        $html .= '<div class="section"><h2>🔄 Test en direct</h2>';
        $html .= '<p>Cliquez sur les liens ci-dessus pour tester. Si "Vérification" affiche ✅ VALIDE mais que le lien ne fonctionne pas, il y a un problème dans le contrôleur.</p>';
        $html .= '</div>';

        $html .= '</body></html>';

        return new Response($html);
    }
}
