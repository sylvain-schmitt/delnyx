<?php

namespace App\Command;

use App\Entity\Project;
use App\Entity\Technology;
use App\Repository\TechnologyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-test-projects',
    description: 'Crée des projets fictifs pour tester la pagination',
)]
class CreateTestProjectsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TechnologyRepository $technologyRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création de projets fictifs pour les tests');

        // Récupérer toutes les technologies disponibles
        $technologies = $this->technologyRepository->findAll();
        
        if (empty($technologies)) {
            $io->error('Aucune technologie trouvée. Créez d\'abord des technologies.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Technologies disponibles : %d', count($technologies)));

        // Projets fictifs à créer
        $testProjects = [
            [
                'titre' => 'Application de Gestion de Tâches',
                'description' => 'Une application web moderne pour la gestion de projets et de tâches en équipe. Interface intuitive avec drag & drop, notifications en temps réel, et intégration avec les outils de communication populaires.',
                'url' => 'https://taskmanager.example.com',
                'technologies' => ['Symfony', 'Vue.js', 'PostgreSQL']
            ],
            [
                'titre' => 'Plateforme de Formation en Ligne',
                'description' => 'Système complet de formation en ligne avec vidéos, quiz interactifs, suivi de progression et certificats. Support multi-langues et responsive design pour tous les appareils.',
                'url' => 'https://elearning.example.com',
                'technologies' => ['Laravel', 'React', 'MySQL']
            ],
            [
                'titre' => 'Application Mobile de Livraison',
                'description' => 'Application mobile native pour la livraison de repas avec géolocalisation en temps réel, système de paiement sécurisé, et interface optimisée pour les livreurs et clients.',
                'url' => 'https://delivery.example.com',
                'technologies' => ['React Native', 'Node.js', 'MongoDB']
            ],
            [
                'titre' => 'Système de Réservation Hôtelière',
                'description' => 'Plateforme de réservation d\'hôtels avec gestion des chambres, système de tarification dynamique, intégration avec les systèmes de paiement et interface d\'administration complète.',
                'url' => 'https://hotelbooking.example.com',
                'technologies' => ['Django', 'Angular', 'PostgreSQL']
            ],
            [
                'titre' => 'Marketplace de Produits Artisanaux',
                'description' => 'Place de marché en ligne pour les artisans et créateurs. Système de gestion des vendeurs, paiements sécurisés, évaluations clients et outils de marketing intégrés.',
                'url' => 'https://artisanmarket.example.com',
                'technologies' => ['Symfony', 'Vue.js', 'MySQL']
            ],
            [
                'titre' => 'Application de Suivi Financier',
                'description' => 'Application de gestion financière personnelle avec tableau de bord interactif, catégorisation automatique des dépenses, prévisions budgétaires et rapports détaillés.',
                'url' => 'https://finance.example.com',
                'technologies' => ['Spring Boot', 'React', 'PostgreSQL']
            ],
            [
                'titre' => 'Système de Gestion d\'Inventaire',
                'description' => 'Solution complète de gestion d\'inventaire pour entreprises avec codes-barres, alertes de stock, rapports automatisés et intégration avec les systèmes de caisse.',
                'url' => 'https://inventory.example.com',
                'technologies' => ['Laravel', 'Vue.js', 'MySQL']
            ],
            [
                'titre' => 'Plateforme de Blog Technique',
                'description' => 'Blog technique moderne avec système de commentaires, recherche avancée, catégorisation par tags, et interface d\'administration pour la gestion du contenu.',
                'url' => 'https://techblog.example.com',
                'technologies' => ['Symfony', 'Twig', 'PostgreSQL']
            ],
            [
                'titre' => 'Application de Planification d\'Événements',
                'description' => 'Outil de planification d\'événements avec gestion des invitations, sondages de disponibilité, rappels automatiques et intégration avec les calendriers populaires.',
                'url' => 'https://eventplanner.example.com',
                'technologies' => ['Node.js', 'React', 'MongoDB']
            ],
            [
                'titre' => 'Système de Support Client',
                'description' => 'Plateforme de support client avec tickets, chat en direct, base de connaissances, et système de suivi des demandes pour une gestion efficace du service client.',
                'url' => 'https://support.example.com',
                'technologies' => ['Django', 'Vue.js', 'PostgreSQL']
            ],
            [
                'titre' => 'Application de Fitness et Nutrition',
                'description' => 'Application complète de fitness avec suivi des entraînements, plans nutritionnels personnalisés, intégration avec les wearables et communauté d\'utilisateurs.',
                'url' => 'https://fitness.example.com',
                'technologies' => ['Flutter', 'Firebase', 'Node.js']
            ],
            [
                'titre' => 'Plateforme de Crowdfunding',
                'description' => 'Site de financement participatif avec gestion des campagnes, système de paiement sécurisé, suivi des objectifs et communication entre porteurs de projets et contributeurs.',
                'url' => 'https://crowdfunding.example.com',
                'technologies' => ['Symfony', 'React', 'MySQL']
            ]
        ];

        $createdCount = 0;
        $progressBar = $io->createProgressBar(count($testProjects));
        $progressBar->start();

        foreach ($testProjects as $projectData) {
            $project = new Project();
            $project->setTitre($projectData['titre']);
            $project->setDescription($projectData['description']);
            $project->setUrl($projectData['url']);
            $project->setStatut(Project::STATUT_PUBLIE);

            // Associer des technologies aléatoires
            $projectTechnologies = $projectData['technologies'];
            foreach ($projectTechnologies as $techName) {
                // Chercher la technologie par nom (insensible à la casse)
                $technology = null;
                foreach ($technologies as $tech) {
                    if (stripos($tech->getNom(), $techName) !== false) {
                        $technology = $tech;
                        break;
                    }
                }
                
                if ($technology) {
                    $project->addTechnology($technology);
                }
            }

            // Si aucune technologie trouvée, associer 2-3 technologies aléatoires
            if ($project->getTechnologies()->isEmpty()) {
                $randomTechnologies = array_rand($technologies, min(3, count($technologies)));
                if (!is_array($randomTechnologies)) {
                    $randomTechnologies = [$randomTechnologies];
                }
                foreach ($randomTechnologies as $index) {
                    $project->addTechnology($technologies[$index]);
                }
            }

            $this->entityManager->persist($project);
            $createdCount++;
            $progressBar->advance();
        }

        $this->entityManager->flush();
        $progressBar->finish();

        $io->newLine(2);
        $io->success(sprintf('%d projets fictifs ont été créés avec succès !', $createdCount));
        $io->info('Vous pouvez maintenant tester la pagination sur /portfolio');

        return Command::SUCCESS;
    }
}
