<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour gérer les fichiers SEO (sitemap, robots.txt)
 */
class SeoController extends AbstractController
{
    /**
     * Génère le sitemap.xml dynamiquement
     */
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        // URLs du site avec leurs priorités et fréquences de mise à jour
        $urls = [
            [
                'loc' => 'https://delnyx.fr/',
                'priority' => '1.0',
                'changefreq' => 'weekly',
                'lastmod' => '2024-01-01'
            ],
            [
                'loc' => 'https://delnyx.fr/services',
                'priority' => '0.9',
                'changefreq' => 'monthly',
                'lastmod' => '2024-01-01'
            ],
            [
                'loc' => 'https://delnyx.fr/portfolio',
                'priority' => '0.9',
                'changefreq' => 'weekly',
                'lastmod' => '2024-01-01'
            ],
            [
                'loc' => 'https://delnyx.fr/about',
                'priority' => '0.7',
                'changefreq' => 'monthly',
                'lastmod' => '2024-01-01'
            ],
            [
                'loc' => 'https://delnyx.fr/contact',
                'priority' => '0.7',
                'changefreq' => 'monthly',
                'lastmod' => '2024-01-01'
            ],
            [
                'loc' => 'https://delnyx.fr/mentions-legales',
                'priority' => '0.3',
                'changefreq' => 'yearly',
                'lastmod' => '2024-01-01'
            ],
            [
                'loc' => 'https://delnyx.fr/politique-confidentialite',
                'priority' => '0.3',
                'changefreq' => 'yearly',
                'lastmod' => '2024-01-01'
            ],
            [
                'loc' => 'https://delnyx.fr/conditions-generales-vente',
                'priority' => '0.3',
                'changefreq' => 'yearly',
                'lastmod' => '2024-01-01'
            ]
        ];

        $response = $this->render('seo/sitemap.xml.twig', [
            'urls' => $urls,
            'hostname' => 'https://delnyx.fr'
        ]);

        // Définir les en-têtes appropriés pour XML
        $response->headers->set('Content-Type', 'application/xml');

        // Cache pendant 24 heures
        $response->setSharedMaxAge(86400);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    /**
     * Génère le robots.txt dynamiquement
     */
    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function robots(): Response
    {
        $rules = [
            'user_agent' => '*',
            'allow' => ['/'],
            'disallow' => ['/admin/', '/api/'],
            'sitemap' => 'https://delnyx.fr/sitemap.xml'
        ];

        $response = $this->render('seo/robots.txt.twig', [
            'rules' => $rules
        ]);

        // Définir les en-têtes appropriés pour le fichier texte
        $response->headers->set('Content-Type', 'text/plain');

        // Cache pendant 24 heures
        $response->setSharedMaxAge(86400);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    /**
     * Génère un sitemap d'index (utile si on a plusieurs sitemaps)
     */
    #[Route('/sitemap-index.xml', name: 'sitemap_index', methods: ['GET'])]
    public function sitemapIndex(): Response
    {
        $sitemaps = [
            [
                'loc' => 'https://delnyx.fr/sitemap.xml',
                'lastmod' => date('Y-m-d\TH:i:s+00:00')
            ]
        ];

        $response = $this->render('seo/sitemap-index.xml.twig', [
            'sitemaps' => $sitemaps
        ]);

        $response->headers->set('Content-Type', 'application/xml');
        $response->setSharedMaxAge(86400);

        return $response;
    }
}
