<?php

namespace App\DataFixtures;

use App\Entity\Technology;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TechnologyFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Technologies populaires avec leurs couleurs et icônes
        $technologies = [
            [
                'nom' => 'Symfony',
                'couleur' => '#000000',
                'icone' => 'skill-icons:symfony-dark'
            ],
            [
                'nom' => 'PHP',
                'couleur' => '#777BB4',
                'icone' => 'skill-icons:php-dark'
            ],
            [
                'nom' => 'Vue.js',
                'couleur' => '#4FC08D',
                'icone' => 'skill-icons:vuejs-dark'
            ],
            [
                'nom' => 'React',
                'couleur' => '#61DAFB',
                'icone' => 'skill-icons:react-dark'
            ],
            [
                'nom' => 'JavaScript',
                'couleur' => '#F7DF1E',
                'icone' => 'skill-icons:javascript'
            ],
            [
                'nom' => 'TypeScript',
                'couleur' => '#3178C6',
                'icone' => 'skill-icons:typescript'
            ],
            [
                'nom' => 'Laravel',
                'couleur' => '#FF2D20',
                'icone' => 'skill-icons:laravel-dark'
            ],
            [
                'nom' => 'Node.js',
                'couleur' => '#339933',
                'icone' => 'skill-icons:nodejs-dark'
            ],
            [
                'nom' => 'PostgreSQL',
                'couleur' => '#4169E1',
                'icone' => 'skill-icons:postgresql-dark'
            ],
            [
                'nom' => 'MySQL',
                'couleur' => '#4479A1',
                'icone' => 'skill-icons:mysql-dark'
            ],
            [
                'nom' => 'MongoDB',
                'couleur' => '#47A248',
                'icone' => 'skill-icons:mongodb'
            ],
            [
                'nom' => 'Docker',
                'couleur' => '#2496ED',
                'icone' => 'skill-icons:docker'
            ],
            [
                'nom' => 'Git',
                'couleur' => '#F05032',
                'icone' => 'skill-icons:git'
            ],
            [
                'nom' => 'Angular',
                'couleur' => '#DD0031',
                'icone' => 'skill-icons:angular-dark'
            ],
            [
                'nom' => 'Django',
                'couleur' => '#092E20',
                'icone' => 'skill-icons:django'
            ],
            [
                'nom' => 'Python',
                'couleur' => '#3776AB',
                'icone' => 'skill-icons:python-dark'
            ],
            [
                'nom' => 'Tailwind CSS',
                'couleur' => '#06B6D4',
                'icone' => 'skill-icons:tailwindcss-dark'
            ],
            [
                'nom' => 'HTML5',
                'couleur' => '#E34F26',
                'icone' => 'skill-icons:html'
            ],
            [
                'nom' => 'CSS3',
                'couleur' => '#1572B6',
                'icone' => 'skill-icons:css'
            ],
            [
                'nom' => 'Redis',
                'couleur' => '#DC382D',
                'icone' => 'skill-icons:redis-dark'
            ],
            [
                'nom' => 'AWS',
                'couleur' => '#FF9900',
                'icone' => 'skill-icons:aws-dark'
            ],
            [
                'nom' => 'Flutter',
                'couleur' => '#02569B',
                'icone' => 'skill-icons:flutter-dark'
            ],
            [
                'nom' => 'React Native',
                'couleur' => '#61DAFB',
                'icone' => 'skill-icons:react-dark'
            ],
            [
                'nom' => 'Spring Boot',
                'couleur' => '#6DB33F',
                'icone' => 'skill-icons:spring-dark'
            ],
            [
                'nom' => 'Java',
                'couleur' => '#ED8B00',
                'icone' => 'skill-icons:java-dark'
            ],
        ];

        foreach ($technologies as $techData) {
            $technology = new Technology();
            $technology->setNom($techData['nom']);
            $technology->setCouleur($techData['couleur']);
            $technology->setIcone($techData['icone']);

            // Enregistrer la référence pour ProjectFixtures
            $this->addReference('technology_' . strtolower(str_replace([' ', '.', '+'], ['_', '', ''], $techData['nom'])), $technology);

            $manager->persist($technology);
        }

        $manager->flush();
    }
}
