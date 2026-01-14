<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Tariff;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TariffFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $tariffs = [
            // Sites vitrine
            [
                'nom' => 'Site vitrine Essentiel',
                'categorie' => 'site_vitrine',
                'description' => 'Site vitrine responsive avec 3 à 5 pages, design personnalisé, formulaire de contact et optimisation SEO de base.',
                'prix' => '800.00',
                'unite' => 'forfait',
                'caracteristiques' => "Design responsive moderne\n3 à 5 pages personnalisées\nFormulaire de contact\nOptimisation SEO de base\nMentions légales et RGPD\nHébergement 1ère année offert",
                'ordre' => 1,
                'actif' => true,
            ],
            [
                'nom' => 'Site vitrine Premium',
                'categorie' => 'site_vitrine',
                'description' => 'Site vitrine complet avec 6 à 10 pages, design sur-mesure, animations, blog intégré et référencement avancé.',
                'prix' => '1500.00',
                'unite' => 'forfait',
                'caracteristiques' => "Design sur-mesure avec animations\n6 à 10 pages personnalisées\nBlog intégré\nFormulaires avancés\nOptimisation SEO avancée\nIntégration Google Analytics\nMentions légales et RGPD\nHébergement 1ère année offert",
                'ordre' => 2,
                'actif' => true,
            ],
            [
                'nom' => 'Site vitrine E-commerce Light',
                'categorie' => 'site_vitrine',
                'description' => 'Site vitrine avec catalogue produits (sans paiement en ligne), jusqu\'à 50 produits.',
                'prix' => '2000.00',
                'unite' => 'forfait',
                'caracteristiques' => "Catalogue jusqu'à 50 produits\nFiches produits détaillées\nRecherche et filtres\nFormulaire de demande de devis\nDesign responsive\nIntégration réseaux sociaux",
                'ordre' => 3,
                'actif' => true,
            ],

            // Systèmes de réservation
            [
                'nom' => 'Module Réservation Simple',
                'categorie' => 'reservation',
                'description' => 'Système de réservation en ligne avec calendrier, gestion des disponibilités et notifications email.',
                'prix' => '1200.00',
                'unite' => 'forfait',
                'caracteristiques' => "Calendrier de disponibilités\nRéservation en ligne\nNotifications email automatiques\nTableau de bord admin\nExport des réservations",
                'ordre' => 10,
                'actif' => true,
            ],
            [
                'nom' => 'Module Réservation Avancé',
                'categorie' => 'reservation',
                'description' => 'Système complet avec paiement en ligne, gestion multi-services et rappels automatiques.',
                'prix' => '2500.00',
                'unite' => 'forfait',
                'caracteristiques' => "Tous les avantages du module Simple\nPaiement en ligne (Stripe/PayPal)\nGestion multi-services\nRéservations récurrentes\nRappels SMS et email\nStatistiques avancées",
                'ordre' => 11,
                'actif' => true,
            ],

            // Applications de gestion
            [
                'nom' => 'CRM sur-mesure',
                'categorie' => 'application_gestion',
                'description' => 'Application de gestion clients personnalisée avec suivi des contacts, devis et factures.',
                'prix' => '4000.00',
                'unite' => 'forfait',
                'caracteristiques' => "Gestion des contacts et clients\nSuivi des opportunités\nGénération de devis et factures\nTableau de bord personnalisé\nExport de données\nFormation incluse",
                'ordre' => 20,
                'actif' => true,
            ],
            [
                'nom' => 'Dashboard Analytics',
                'categorie' => 'application_gestion',
                'description' => 'Tableau de bord personnalisé avec visualisation de données et KPI métier.',
                'prix' => '2500.00',
                'unite' => 'forfait',
                'caracteristiques' => "Visualisation de données temps réel\nGraphiques et KPI personnalisés\nConnexion à vos sources de données\nExport PDF et Excel\nAccès multi-utilisateurs",
                'ordre' => 21,
                'actif' => true,
            ],

            // Maintenance
            [
                'nom' => 'Maintenance Essentielle',
                'categorie' => 'maintenance',
                'description' => 'Maintenance mensuelle incluant mises à jour de sécurité, sauvegardes et support par email.',
                'prix' => '50.00',
                'unite' => 'mois',
                'caracteristiques' => "Mises à jour de sécurité\nSauvegarde hebdomadaire\nSupport par email (48h)\nMonitoring uptime\nRapport mensuel",
                'ordre' => 30,
                'actif' => true,
            ],
            [
                'nom' => 'Maintenance Premium',
                'categorie' => 'maintenance',
                'description' => 'Maintenance complète avec support prioritaire, modifications mineures incluses et sauvegarde quotidienne.',
                'prix' => '120.00',
                'unite' => 'mois',
                'caracteristiques' => "Tous les avantages Essentielle\nSauvegarde quotidienne\nSupport prioritaire (24h)\n2h de modifications mineures/mois\nMonitoring avancé\nCertificat SSL inclus",
                'ordre' => 31,
                'actif' => true,
            ],
            [
                'nom' => 'Intervention ponctuelle',
                'categorie' => 'maintenance',
                'description' => 'Intervention à l\'heure pour dépannage, modifications ou évolutions.',
                'prix' => '60.00',
                'unite' => 'heure',
                'caracteristiques' => "Dépannage urgent\nModifications spécifiques\nConseil et accompagnement\nFacturation au temps réel",
                'ordre' => 32,
                'actif' => true,
            ],
        ];

        foreach ($tariffs as $data) {
            $tariff = new Tariff();
            $tariff->setNom($data['nom']);
            $tariff->setCategorie($data['categorie']);
            $tariff->setDescription($data['description']);
            $tariff->setPrix($data['prix']);
            $tariff->setUnite($data['unite']);
            $tariff->setCaracteristiques($data['caracteristiques']);
            $tariff->setOrdre($data['ordre']);
            $tariff->setActif($data['actif']);

            $manager->persist($tariff);
        }

        $manager->flush();
    }
}
