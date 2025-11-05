<?php

namespace App\Controller\Admin;

use App\Entity\Technology;
use App\Form\TechnologyType;
use App\Repository\TechnologyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/technology', name: 'admin_technology_')]
class TechnologyController extends AbstractController
{
    public function __construct(
        private TechnologyRepository $technologyRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20; // 20 technologies par page

        // Récupérer le nombre total de technologies
        $totalTechnologies = $this->technologyRepository->count([]);

        // Calculer le nombre total de pages
        $totalPages = (int) ceil($totalTechnologies / $limit);

        // S'assurer que la page demandée existe
        $page = min($page, max(1, $totalPages));

        // Récupérer les technologies de la page courante (triées par nom)
        $technologies = $this->technologyRepository->findBy(
            [],
            ['nom' => 'ASC'],
            $limit,
            ($page - 1) * $limit
        );

        return $this->render('admin/technology/index.html.twig', [
            'technologies' => $technologies,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_technologies' => $totalTechnologies,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $technology = new Technology();
        $form = $this->createForm(TechnologyType::class, $technology);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($technology);
            $this->entityManager->flush();

            $this->addFlash('success', 'Technologie créée avec succès');
            return $this->redirectToRoute('admin_technology_index');
        }

        return $this->render('admin/technology/form.html.twig', [
            'technology' => $technology,
            'form' => $form,
            'title' => 'Nouvelle Technologie',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Technology $technology): Response
    {
        $form = $this->createForm(TechnologyType::class, $technology);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Technologie modifiée avec succès');
            return $this->redirectToRoute('admin_technology_index');
        }

        return $this->render('admin/technology/form.html.twig', [
            'technology' => $technology,
            'form' => $form,
            'title' => 'Modifier la Technologie',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Technology $technology): Response
    {
        // Vérifier si la technologie est utilisée dans des projets
        if ($technology->getProjets()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer cette technologie car elle est utilisée dans ' . $technology->getProjets()->count() . ' projet(s)');
            return $this->redirectToRoute('admin_technology_index');
        }

        if ($this->isCsrfTokenValid('delete' . $technology->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($technology);
            $this->entityManager->flush();

            $this->addFlash('success', 'Technologie supprimée avec succès');
        }

        return $this->redirectToRoute('admin_technology_index');
    }

    /**
     * Endpoint pour récupérer le HTML d'une icône (pour l'aperçu en temps réel)
     */
    #[Route('/preview-icon', name: 'preview_icon', methods: ['GET'], priority: 10)]
    public function previewIcon(Request $request): Response
    {
        $iconName = $request->query->get('icon', 'lucide:code');
        $color = $request->query->get('color', '#3b82f6');

        // Nettoyer le nom de l'icône (enlever les caractères spéciaux)
        $iconName = trim($iconName);

        // Si le nom contient du code Twig, utiliser la valeur par défaut
        if (str_contains($iconName, '{{') || str_contains($iconName, 'ux_icon')) {
            $iconName = 'lucide:code';
        }

        try {
            // Essayer de rendre l'icône
            $html = $this->renderView('admin/technology/_icon_preview.html.twig', [
                'iconName' => $iconName,
                'color' => $color,
            ]);

            return new Response($html, 200, ['Content-Type' => 'text/html']);
        } catch (\Exception $e) {
            // Si l'icône n'existe pas, retourner l'icône par défaut
            $html = $this->renderView('admin/technology/_icon_preview.html.twig', [
                'iconName' => 'lucide:code',
                'color' => $color,
            ]);

            return new Response($html, 200, ['Content-Type' => 'text/html']);
        }
    }
}
