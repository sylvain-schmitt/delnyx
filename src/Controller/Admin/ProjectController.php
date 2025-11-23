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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

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
            // Traiter l'image principale uploadée (unique)
            $this->handleMainImageUpload($project, $form);

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
            // Traiter une nouvelle image principale si fournie
            $this->handleMainImageUpload($project, $form);

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
            $uploadsBaseDir = $this->getParameter('kernel.project_dir') . '/public';

            // Supprimer les images associées (fichiers physiques + miniatures)
            foreach ($project->getImages() as $image) {
                // Supprimer l'image principale
                $imagePath = $uploadsBaseDir . $image->getImageUrl();
                if (file_exists($imagePath)) {
                    @unlink($imagePath);
                }

                // Supprimer la miniature si elle existe
                $thumbnailPath = $uploadsBaseDir . $image->getThumbnailUrl();
                if (file_exists($thumbnailPath)) {
                    @unlink($thumbnailPath);
                }
            }

            // Supprimer le projet (les ProjectImage seront supprimées en cascade)
            $this->entityManager->remove($project);
            $this->entityManager->flush();

            $this->addFlash('success', 'Projet supprimé avec succès');
        }

        return $this->redirectToRoute('admin_project_index');
    }

    /**
     * Gère l'upload d'une image principale unique pour le projet
     */
    private function handleMainImageUpload(Project $project, $form): void
    {
        $file = $form->get('imageFile')->getData();
        $alt = $form->get('imageAlt')->getData();

        if (!$file) {
            // Pas d'upload => on met juste à jour l'alt éventuel sur l'image existante
            $main = $project->getImagePrincipale();
            if ($main && $alt !== null) {
                $main->setAltText($alt);
            }
            return;
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/projects';
        // Ne tente pas de créer le dossier si l'environnement ne le permet pas
        // On vérifie simplement qu'il est présent et inscriptible
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            $this->addFlash('error', "Le dossier d'upload n'est pas accessible en écriture: " . $uploadDir);
            return;
        }

        $slugger = new AsciiSlugger();

        // Générer un nom unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($uploadDir, $newFilename);
        } catch (FileException $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage());
            return;
        }

        // Mettre à jour / créer l'image principale
        $main = $project->getImagePrincipale();
        if (!$main) {
            $main = new ProjectImage();
            $main->setProjet($project);
            $main->setOrdre(0);
            $project->addImage($main);
        } else {
            // Supprimer l'ancien fichier si nécessaire
            if ($main->getFichier()) {
                $oldFile = $uploadDir . '/' . $main->getFichier();
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }

        $main->setFichier($newFilename);
        if ($alt !== null) {
            $main->setAltText($alt);
        }
    }
}
