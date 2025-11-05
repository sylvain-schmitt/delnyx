<?php

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\ProjectImage;
use App\Entity\Technology;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;

class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Types de projets avec leurs descriptions
        $projectTemplates = [
            [
                'titre' => 'Application de Gestion de Tâches',
                'description' => 'Une application web moderne pour la gestion de projets et de tâches en équipe. Interface intuitive avec drag & drop, notifications en temps réel, et intégration avec les outils de communication populaires.',
                'technologies' => ['symfony', 'vuejs', 'postgresql']
            ],
            [
                'titre' => 'Plateforme de Formation en Ligne',
                'description' => 'Système complet de formation en ligne avec vidéos, quiz interactifs, suivi de progression et certificats. Support multi-langues et responsive design pour tous les appareils.',
                'technologies' => ['laravel', 'react', 'mysql']
            ],
            [
                'titre' => 'Application Mobile de Livraison',
                'description' => 'Application mobile native pour la livraison de repas avec géolocalisation en temps réel, système de paiement sécurisé, et interface optimisée pour les livreurs et clients.',
                'technologies' => ['react_native', 'nodejs', 'mongodb']
            ],
            [
                'titre' => 'Système de Réservation Hôtelière',
                'description' => 'Plateforme de réservation d\'hôtels avec gestion des chambres, système de tarification dynamique, intégration avec les systèmes de paiement et interface d\'administration complète.',
                'technologies' => ['django', 'angular', 'postgresql']
            ],
            [
                'titre' => 'Marketplace de Produits Artisanaux',
                'description' => 'Place de marché en ligne pour les artisans et créateurs. Système de gestion des vendeurs, paiements sécurisés, évaluations clients et outils de marketing intégrés.',
                'technologies' => ['symfony', 'vuejs', 'mysql']
            ],
            [
                'titre' => 'Application de Suivi Financier',
                'description' => 'Application de gestion financière personnelle avec tableau de bord interactif, catégorisation automatique des dépenses, prévisions budgétaires et rapports détaillés.',
                'technologies' => ['spring_boot', 'react', 'postgresql']
            ],
            [
                'titre' => 'Système de Gestion d\'Inventaire',
                'description' => 'Solution complète de gestion d\'inventaire pour entreprises avec codes-barres, alertes de stock, rapports automatisés et intégration avec les systèmes de caisse.',
                'technologies' => ['laravel', 'vuejs', 'mysql']
            ],
            [
                'titre' => 'Plateforme de Blog Technique',
                'description' => 'Blog technique moderne avec système de commentaires, recherche avancée, catégorisation par tags, et interface d\'administration pour la gestion du contenu.',
                'technologies' => ['symfony', 'html5', 'postgresql']
            ],
            [
                'titre' => 'Application de Planification d\'Événements',
                'description' => 'Outil de planification d\'événements avec gestion des invitations, sondages de disponibilité, rappels automatiques et intégration avec les calendriers populaires.',
                'technologies' => ['nodejs', 'react', 'mongodb']
            ],
            [
                'titre' => 'Système de Support Client',
                'description' => 'Plateforme de support client avec tickets, chat en direct, base de connaissances, et système de suivi des demandes pour une gestion efficace du service client.',
                'technologies' => ['django', 'vuejs', 'postgresql']
            ],
            [
                'titre' => 'Application de Fitness et Nutrition',
                'description' => 'Application complète de fitness avec suivi des entraînements, plans nutritionnels personnalisés, intégration avec les wearables et communauté d\'utilisateurs.',
                'technologies' => ['flutter', 'nodejs']
            ],
            [
                'titre' => 'Plateforme de Crowdfunding',
                'description' => 'Site de financement participatif avec gestion des campagnes, système de paiement sécurisé, suivi des objectifs et communication entre porteurs de projets et contributeurs.',
                'technologies' => ['symfony', 'react', 'mysql']
            ],
            [
                'titre' => 'Application E-commerce Moderne',
                'description' => 'Boutique en ligne complète avec gestion de catalogue, panier d\'achat, système de paiement, gestion des commandes et interface d\'administration avancée.',
                'technologies' => ['symfony', 'vuejs', 'postgresql', 'redis']
            ],
            [
                'titre' => 'Système de Gestion de Contenu',
                'description' => 'CMS sur mesure avec éditeur WYSIWYG, gestion des médias, système de rôles et permissions, et API REST pour intégrations tierces.',
                'technologies' => ['laravel', 'react', 'mysql']
            ],
            [
                'titre' => 'Application de Réseau Social',
                'description' => 'Plateforme sociale avec profils utilisateurs, publications, commentaires, système de suivi et notifications en temps réel.',
                'technologies' => ['nodejs', 'react', 'mongodb', 'redis']
            ],
            [
                'titre' => 'Dashboard Analytique',
                'description' => 'Tableau de bord interactif pour visualiser et analyser des données métier avec graphiques dynamiques, filtres avancés et export de rapports.',
                'technologies' => ['vuejs', 'python', 'postgresql']
            ],
            [
                'titre' => 'Application de Chat en Temps Réel',
                'description' => 'Messagerie instantanée avec salons de discussion, partage de fichiers, notifications push et historique de conversations.',
                'technologies' => ['nodejs', 'react', 'mongodb', 'redis']
            ],
            [
                'titre' => 'Système de Gestion de Projets',
                'description' => 'Outil collaboratif pour la gestion de projets avec tableaux Kanban, suivi du temps, affectation de tâches et rapports de progression.',
                'technologies' => ['symfony', 'vuejs', 'postgresql']
            ],
            [
                'titre' => 'Plateforme de Streaming Vidéo',
                'description' => 'Service de streaming avec lecture adaptative, gestion des playlists, sous-titres multilingues et système de recommandations.',
                'technologies' => ['django', 'react', 'postgresql', 'aws']
            ],
            [
                'titre' => 'Application de Location de Véhicules',
                'description' => 'Plateforme de location de véhicules avec réservation en ligne, géolocalisation, gestion de flotte et système de paiement intégré.',
                'technologies' => ['laravel', 'vuejs', 'mysql']
            ],
        ];

        // Générer 50 projets pour tester la pagination
        for ($i = 0; $i < 50; $i++) {
            $template = $faker->randomElement($projectTemplates);
            
            $project = new Project();
            $project->setTitre($template['titre'] . ($i > 0 ? ' #' . ($i + 1) : ''));
            $project->setDescription($template['description']);
            
            // URL optionnelle (80% de chance)
            if ($faker->boolean(80)) {
                $project->setUrl('https://' . strtolower(str_replace([' ', '#'], ['-', ''], $template['titre'])) . '.example.com');
            }
            
            // Statut (70% publiés, 30% brouillons)
            $project->setStatut($faker->boolean(70) ? Project::STATUT_PUBLIE : Project::STATUT_BROUILLON);
            
            // Date de création aléatoire (6 derniers mois)
            $dateCreation = $faker->dateTimeBetween('-6 months', 'now');
            $project->setDateCreation(\DateTimeImmutable::createFromMutable($dateCreation));
            
            // Associer des technologies
            foreach ($template['technologies'] as $techKey) {
                try {
                    $technology = $this->getReference('technology_' . $techKey, Technology::class);
                    if ($technology) {
                        $project->addTechnology($technology);
                    }
                } catch (\Exception $e) {
                    // Technologie non trouvée, on continue
                }
            }
            
            // Si aucune technologie n'a été associée, en ajouter 2-4 aléatoirement
            if ($project->getTechnologies()->isEmpty()) {
                $allTechnologies = [];
                $techKeys = ['symfony', 'php', 'vuejs', 'react', 'javascript', 'typescript', 'laravel', 'nodejs', 
                             'postgresql', 'mysql', 'mongodb', 'docker', 'git', 'angular', 'django', 'python', 
                             'tailwind_css', 'html5', 'css3', 'redis', 'aws', 'flutter', 'react_native', 
                             'spring_boot', 'java'];
                
                foreach ($techKeys as $key) {
                    try {
                        $tech = $this->getReference('technology_' . $key, Technology::class);
                        if ($tech) {
                            $allTechnologies[] = $tech;
                        }
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
                
                if (!empty($allTechnologies)) {
                    $randomTechnologies = $faker->randomElements($allTechnologies, min($faker->numberBetween(2, 4), count($allTechnologies)));
                    foreach ($randomTechnologies as $tech) {
                        $project->addTechnology($tech);
                    }
                }
            }
            
            // Ajouter une image pour 80% des projets
            if ($faker->boolean(80)) {
                $image = $this->createProjectImage($project, $faker);
                if ($image) {
                    $project->addImage($image);
                    $manager->persist($image);
                }
            }
            
            $manager->persist($project);
        }

        $manager->flush();
    }

    /**
     * Crée une image pour un projet en téléchargeant une image depuis un service placeholder
     */
    private function createProjectImage(Project $project, $faker): ?ProjectImage
    {
        $projectDir = $this->kernel->getProjectDir();
        $uploadDir = $projectDir . '/public/uploads/projects';
        
        // Vérifier que le dossier existe et est accessible
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            return null;
        }

        try {
            $client = HttpClient::create();
            
            // Télécharger une image depuis Picsum Photos (service de placeholder)
            // Dimensions: 800x600 pour un bon ratio
            $imageUrl = 'https://picsum.photos/800/600?random=' . uniqid();
            
            $response = $client->request('GET', $imageUrl);
            
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            // Générer un nom de fichier unique
            $extension = 'jpg'; // Picsum retourne toujours des JPG
            $filename = 'project-' . uniqid() . '-' . $faker->uuid() . '.' . $extension;
            $filePath = $uploadDir . '/' . $filename;

            // Sauvegarder l'image
            file_put_contents($filePath, $response->getContent());

            // Créer l'entité ProjectImage
            $projectImage = new ProjectImage();
            $projectImage->setProjet($project);
            $projectImage->setFichier($filename);
            $projectImage->setAltText($project->getTitre() . ' - Capture d\'écran');
            $projectImage->setOrdre(0);

            return $projectImage;
        } catch (\Exception $e) {
            // En cas d'erreur (pas de connexion internet, etc.), on retourne null
            // L'image ne sera simplement pas créée
            return null;
        }
    }

    public function getDependencies(): array
    {
        return [
            TechnologyFixtures::class,
        ];
    }
}

