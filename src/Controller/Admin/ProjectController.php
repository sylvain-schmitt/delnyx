<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Entity\ProjectImage;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/project', name: 'admin_project_')]
class ProjectController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12; // 12 projets par page

        // Récupérer le nombre total de projets
        $totalProjects = $this->projectRepository->count([]);

        // Calculer le nombre total de pages
        $totalPages = (int) ceil($totalProjects / $limit);

        // S'assurer que la page demandée existe
        $page = min($page, max(1, $totalPages));

        // Récupérer les projets de la page courante
        $projects = $this->projectRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        return $this->render('admin/project/index.html.twig', [
            'projects' => $projects,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_projects' => $totalProjects,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traiter les images uploadées
            $this->handleImageUploads($project, $form);

            $this->entityManager->persist($project);
            $this->entityManager->flush();

            $this->addFlash('success', 'Projet créé avec succès');
            return $this->redirectToRoute('admin_project_index');
        }

        return $this->render('admin/project/form.html.twig', [
            'project' => $project,
            'form' => $form,
            'title' => 'Nouveau Projet',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Project $project): Response
    {
        $form = $this->createForm(ProjectType::class, $project, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traiter les nouvelles images uploadées
            $this->handleImageUploads($project, $form);

            $this->entityManager->flush();

            $this->addFlash('success', 'Projet modifié avec succès');
            return $this->redirectToRoute('admin_project_index');
        }

        return $this->render('admin/project/form.html.twig', [
            'project' => $project,
            'form' => $form,
            'title' => 'Modifier le Projet',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Project $project): Response
    {
        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
            // Supprimer les images associées
            foreach ($project->getImages() as $image) {
                $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/projects/' . $image->getFichier();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $this->entityManager->remove($project);
            $this->entityManager->flush();

            $this->addFlash('success', 'Projet supprimé avec succès');
        }

        return $this->redirectToRoute('admin_project_index');
    }

    /**
     * Gère l'upload des images pour un projet
     */
    private function handleImageUploads(Project $project, $form): void
    {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/projects';
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Créer une instance de slugger
        $slugger = new AsciiSlugger();

        // Parcourir les images du formulaire
        $imagesForm = $form->get('images');
        
        foreach ($imagesForm as $imageForm) {
            $image = $imageForm->getData();
            $file = $imageForm->get('file')->getData();

            // Si un fichier est uploadé
            if ($file) {
                // Supprimer l'ancien fichier si l'image existe déjà
                if ($image->getId() && $image->getFichier()) {
                    $oldFilePath = $uploadDir . '/' . $image->getFichier();
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                // Générer un nom de fichier unique
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    // Déplacer le fichier
                    $file->move($uploadDir, $newFilename);
                    $image->setFichier($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage());
                }
            }

            // Si l'image n'a pas de fichier et qu'elle est nouvelle, on la supprime
            if (!$image->getFichier() && !$image->getId()) {
                $project->removeImage($image);
                continue;
            }

            // Associer l'image au projet si ce n'est pas déjà fait
            if ($image->getProjet() !== $project) {
                $image->setProjet($project);
            }

            // Si l'ordre n'est pas défini, utiliser la prochaine valeur disponible
            if ($image->getOrdre() === null) {
                $maxOrdre = 0;
                foreach ($project->getImages() as $existingImage) {
                    if ($existingImage !== $image && $existingImage->getOrdre() >= $maxOrdre) {
                        $maxOrdre = $existingImage->getOrdre() + 1;
                    }
                }
                $image->setOrdre($maxOrdre);
            }
        }
    }
}
