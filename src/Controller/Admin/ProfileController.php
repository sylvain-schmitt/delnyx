<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/profile', name: 'admin_profile_')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private ParameterBagInterface $parameterBag
    ) {}

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer le changement de mot de passe si fourni
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Gérer l'upload de l'avatar
            $avatarFile = $form->get('avatar')->getData();
            if ($avatarFile) {
                $avatarPath = $this->handleAvatarUpload($avatarFile, $user);
                if ($avatarPath) {
                    $user->setAvatarPath($avatarPath);
                }
            }

            $this->em->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('admin_profile_index');
        }

        return $this->render('admin/profile/index.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    /**
     * Gère l'upload de l'avatar
     */
    private function handleAvatarUpload($file, $user): ?string
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');

        // Supprimer l'ancien avatar si il existe
        if ($user->getAvatarPath()) {
            $oldAvatarPath = $projectDir . '/public' . $user->getAvatarPath();
            if (file_exists($oldAvatarPath)) {
                @unlink($oldAvatarPath);
            }
        }

        // Générer un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        if (empty($safeFilename)) {
            $safeFilename = 'avatar';
        }
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Déplacer le fichier vers le répertoire public/uploads/avatars
        $uploadDir = $projectDir . '/public/uploads/avatars';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $this->addFlash('error', 'Impossible de créer le répertoire d\'upload.');
                return null;
            }
        }

        // Vérifier les permissions d'écriture
        if (!is_writable($uploadDir)) {
            $this->addFlash('error', 'Le répertoire d\'upload n\'est pas accessible en écriture.');
            return null;
        }

        try {
            $file->move($uploadDir, $newFilename);
            return '/uploads/avatars/' . $newFilename;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload de l\'avatar : ' . $e->getMessage());
            return null;
        }
    }
}

