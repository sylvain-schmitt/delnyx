<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Technology;
use App\Repository\ProjectRepository;
use App\Repository\TechnologyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortfolioController extends AbstractController
{
    #[Route('/portfolio', name: 'app_portfolio')]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        TechnologyRepository $technologyRepository
    ): Response {
        // Récupérer le filtre par technologie et la page
        $technologyFilter = $request->query->get('technology');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 6; // Nombre de projets par page

        // Récupérer toutes les technologies pour les filtres
        $technologies = $technologyRepository->findAll();

        // Récupérer les projets avec pagination en utilisant les repositories
        if ($technologyFilter) {
            $technology = $technologyRepository->find($technologyFilter);
            if ($technology) {
                $result = $projectRepository->findByTechnologyWithPagination($technology, $page, $limit);
                $projects = $result['projects'];
                $totalProjects = $result['total'];
            } else {
                $projects = [];
                $totalProjects = 0;
            }
        } else {
            $result = $projectRepository->findPublishedWithPagination($page, $limit);
            $projects = $result['projects'];
            $totalProjects = $result['total'];
        }

        $totalPages = ceil($totalProjects / $limit);

        return $this->render('portfolio/index.html.twig', [
            'projects' => $projects,
            'technologies' => $technologies,
            'selectedTechnology' => $technologyFilter,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProjects' => $totalProjects,
            'limit' => $limit,
        ]);
    }
}
