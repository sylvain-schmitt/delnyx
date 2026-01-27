<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tariff;
use App\Form\TariffType;
use App\Repository\TariffRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tariff', name: 'admin_tariff_')]
class TariffController extends AbstractController
{
    public function __construct(
        private TariffRepository $tariffRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        $categorie = $request->query->get('categorie');

        // Filtrage par catégorie
        $criteria = [];
        if ($categorie) {
            $criteria['categorie'] = $categorie;
        }

        $totalTariffs = $this->tariffRepository->count($criteria);
        $totalPages = (int) ceil($totalTariffs / $limit);
        $page = min($page, max(1, $totalPages));

        $tariffs = $this->tariffRepository->findBy(
            $criteria,
            ['ordre' => 'ASC', 'nom' => 'ASC'],
            $limit,
            ($page - 1) * $limit
        );

        return $this->render('admin/tariff/index.html.twig', [
            'tariffs' => $tariffs,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_tariffs' => $totalTariffs,
            'categories' => Tariff::getCategories(),
            'current_categorie' => $categorie,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $tariff = new Tariff();
        $form = $this->createForm(TariffType::class, $tariff);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($tariff);
            $this->entityManager->flush();

            $this->addFlash('success', 'Tarif créé avec succès');
            return $this->redirectToRoute('admin_tariff_index');
        }

        return $this->render('admin/tariff/form.html.twig', [
            'tariff' => $tariff,
            'form' => $form,
            'title' => 'Nouveau Tarif',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Tariff $tariff): Response
    {
        $form = $this->createForm(TariffType::class, $tariff);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Tarif modifié avec succès');
            return $this->redirectToRoute('admin_tariff_index');
        }

        return $this->render('admin/tariff/form.html.twig', [
            'tariff' => $tariff,
            'form' => $form,
            'title' => 'Modifier le Tarif',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Tariff $tariff): Response
    {
        if ($this->isCsrfTokenValid('delete' . $tariff->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($tariff);
            $this->entityManager->flush();

            $this->addFlash('success', 'Tarif supprimé avec succès');
        }

        return $this->redirectToRoute('admin_tariff_index');
    }

    #[Route('/{id}/toggle', name: 'toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, Tariff $tariff): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $tariff->getId(), $request->request->get('_token'))) {
            $tariff->setActif(!$tariff->isActif());
            $this->entityManager->flush();

            $this->addFlash('success', $tariff->isActif() ? 'Tarif activé' : 'Tarif désactivé');
        }

        return $this->redirectToRoute('admin_tariff_index');
    }

    #[Route('/{id}/duplicate', name: 'duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(Request $request, Tariff $tariff): Response
    {
        if ($this->isCsrfTokenValid('duplicate' . $tariff->getId(), $request->request->get('_token'))) {
            $newTariff = new Tariff();
            $newTariff->setNom($tariff->getNom() . ' (copie)');
            $newTariff->setCategorie($tariff->getCategorie());
            $newTariff->setDescription($tariff->getDescription());
            $newTariff->setPrix($tariff->getPrix());
            $newTariff->setUnite($tariff->getUnite());
            $newTariff->setCaracteristiques($tariff->getCaracteristiques());
            $newTariff->setActif(false); // Désactivé par défaut
            $newTariff->setOrdre($tariff->getOrdre() + 1);

            $this->entityManager->persist($newTariff);
            $this->entityManager->flush();

            $this->addFlash('success', 'Tarif dupliqué avec succès');
        }

        return $this->redirectToRoute('admin_tariff_edit', ['id' => $newTariff->getId()]);
    }

    /**
     * API endpoint pour la recherche de tarifs (autocomplete)
     */
    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function apiSearch(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $categorie = $request->query->get('categorie');
        $limit = min(20, $request->query->getInt('limit', 10));

        $qb = $this->tariffRepository->createQueryBuilder('t')
            ->where('t.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('t.nom', 'ASC')
            ->setMaxResults($limit);

        if ($query) {
            $qb->andWhere('t.nom LIKE :query OR t.description LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        if ($categorie) {
            $qb->andWhere('t.categorie = :categorie')
                ->setParameter('categorie', $categorie);
        }

        $tariffs = $qb->getQuery()->getResult();

        $data = array_map(function (Tariff $tariff) {
            return [
                'id' => $tariff->getId(),
                'nom' => $tariff->getNom(),
                'description' => $tariff->getDescription(),
                'prix' => $tariff->getPrix(),
                'unite' => $tariff->getUnite(),
                'uniteLabel' => $tariff->getUniteLabel(),
                'categorie' => $tariff->getCategorie(),
                'categorieLabel' => $tariff->getCategorieLabel(),
            ];
        }, $tariffs);

        return new JsonResponse($data);
    }

    /**
     * API endpoint pour récupérer les détails d'un tarif
     */
    #[Route('/api/{id}', name: 'api_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function apiGet(Tariff $tariff): JsonResponse
    {
        return new JsonResponse([
            'id' => $tariff->getId(),
            'nom' => $tariff->getNom(),
            'description' => $tariff->getDescription(),
            'prix' => $tariff->getPrix(),
            'unite' => $tariff->getUnite(),
            'uniteLabel' => $tariff->getUniteLabel(),
            'categorie' => $tariff->getCategorie(),
            'categorieLabel' => $tariff->getCategorieLabel(),
            'caracteristiques' => $tariff->getCaracteristiques(),
            'hasRecurrence' => $tariff->isHasRecurrence(),
            'prixMensuel' => $tariff->getPrixMensuel(),
            'prixAnnuel' => $tariff->getPrixAnnuel(),
        ]);
    }
}
