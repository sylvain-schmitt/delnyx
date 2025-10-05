<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\ClientStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ClientFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        // Villes françaises réalistes
        $villes = [
            ['nom' => 'Paris', 'cp' => '75001'],
            ['nom' => 'Lyon', 'cp' => '69001'],
            ['nom' => 'Marseille', 'cp' => '13001'],
            ['nom' => 'Toulouse', 'cp' => '31000'],
            ['nom' => 'Nice', 'cp' => '06000'],
            ['nom' => 'Nantes', 'cp' => '44000'],
            ['nom' => 'Bordeaux', 'cp' => '33000'],
            ['nom' => 'Lille', 'cp' => '59000'],
            ['nom' => 'Rennes', 'cp' => '35000'],
            ['nom' => 'Strasbourg', 'cp' => '67000'],
            ['nom' => 'Montpellier', 'cp' => '34000'],
            ['nom' => 'Reims', 'cp' => '51100'],
            ['nom' => 'Le Havre', 'cp' => '76600'],
            ['nom' => 'Saint-Étienne', 'cp' => '42000'],
            ['nom' => 'Toulon', 'cp' => '83000'],
            ['nom' => 'Grenoble', 'cp' => '38000'],
            ['nom' => 'Dijon', 'cp' => '21000'],
            ['nom' => 'Angers', 'cp' => '49000'],
            ['nom' => 'Nîmes', 'cp' => '30000'],
            ['nom' => 'Villeurbanne', 'cp' => '69100'],
        ];

        // Générer 75 clients
        for ($i = 0; $i < 75; $i++) {
            $client = new Client();
            
            // Informations personnelles
            $client->setNom($faker->lastName());
            $client->setPrenom($faker->firstName());
            $client->setEmail($faker->unique()->email());
            $client->setTelephone($faker->optional(0.8)->phoneNumber());
            
            // Adresse
            $ville = $faker->randomElement($villes);
            $client->setAdresse($faker->optional(0.7)->streetAddress());
            $client->setCodePostal($faker->optional(0.7)->numerify($ville['cp']));
            $client->setVille($faker->optional(0.7)->randomElement([$ville['nom']]));
            $client->setPays('France');
            
            // SIRET (optionnel, 70% de chance)
            if ($faker->boolean(70)) {
                $client->setSiret($faker->numerify('##############'));
            }
            
            // Statut (distribution réaliste: 40% prospects, 50% actifs, 10% inactifs)
            $rand = $faker->numberBetween(1, 100);
            if ($rand <= 40) {
                $client->setStatut(ClientStatus::PROSPECT);
            } elseif ($rand <= 90) {
                $client->setStatut(ClientStatus::ACTIF);
            } else {
                $client->setStatut(ClientStatus::INACTIF);
            }
            
            // Notes (optionnel, 30% de chance)
            if ($faker->boolean(30)) {
                $notes = [
                    'Client recommandé par un partenaire',
                    'Intéressé par une refonte complète',
                    'Budget limité, projet en plusieurs phases',
                    'Très réactif, bon contact',
                    'Demande un devis pour site e-commerce',
                    'Client fidèle depuis 2 ans',
                    'Souhaite une application mobile',
                    'Projet urgent, deadline serrée',
                    'Nécessite un accompagnement SEO',
                    'Intéressé par React et Symfony',
                ];
                $client->setNotes($faker->randomElement($notes));
            }
            
            $manager->persist($client);
        }

        $manager->flush();
    }
}

