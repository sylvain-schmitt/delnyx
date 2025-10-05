<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Repository\ClientRepository;
use App\Repository\DevisRepository;
use App\Repository\FactureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private DevisRepository $devisRepository,
        private FactureRepository $factureRepository
    ) {}

    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        // Statistiques générales
        $stats = [
            'clients' => $this->clientRepository->count([]),
            'devis' => $this->devisRepository->count([]),
            'factures' => $this->factureRepository->count([]),
            'ca_mensuel' => $this->getCAMensuel(),
        ];

        // Devis récents (5 derniers)
        $recent_devis = $this->devisRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            5
        );

        // Factures récentes (5 dernières)
        $recent_factures = $this->factureRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            5
        );

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'recent_devis' => $recent_devis,
            'recent_factures' => $recent_factures,
        ]);
    }

    /**
     * Calcule le CA mensuel
     */
    private function getCAMensuel(): int
    {
        $debutMois = new \DateTime('first day of this month');
        $finMois = new \DateTime('last day of this month');

        $factures = $this->factureRepository->createQueryBuilder('f')
            ->where('f.dateCreation BETWEEN :debut AND :fin')
            ->andWhere('f.statutEnum = :statut')
            ->setParameter('debut', $debutMois)
            ->setParameter('fin', $finMois)
            ->setParameter('statut', 'payee')
            ->getQuery()
            ->getResult();

        $ca = 0;
        foreach ($factures as $facture) {
            $ca += $facture->getMontantTTC();
        }

        return $ca;
    }
}