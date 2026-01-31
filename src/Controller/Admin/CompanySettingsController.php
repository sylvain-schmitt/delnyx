<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CompanySettings;
use App\Form\CompanySettingsType;
use App\Repository\CompanySettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/company', name: 'admin_company_')]
#[IsGranted('ROLE_USER')]
class CompanySettingsController extends AbstractController
{
    public function __construct(
        private CompanySettingsRepository $companySettingsRepository,
        private EntityManagerInterface $em,
        private ParameterBagInterface $parameterBag
    ) {}

    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        // Générer un UUID unique pour chaque utilisateur/entreprise
        // Pour l'instant, on génère un UUID basé sur l'email de l'utilisateur pour qu'il soit stable
        $user = $this->getUser();
        if ($user && method_exists($user, 'getEmail')) {
            // Générer un UUID v5 basé sur l'email pour qu'il soit déterministe
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'); // Namespace DNS
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        } else {
            // Fallback : générer un UUID v4 aléatoire
            $companyId = Uuid::v4()->toString();
        }

        $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);

        // Si les paramètres n'existent pas, créer une nouvelle entité
        if (!$companySettings) {
            $companySettings = new CompanySettings();
            $companySettings->setCompanyId($companyId);

            // Pré-remplir l'email avec celui de l'utilisateur connecté
            $user = $this->getUser();
            if ($user && method_exists($user, 'getEmail')) {
                $companySettings->setEmail($user->getEmail());
            }
        } else {
            // Si l'email n'est pas renseigné, pré-remplir avec celui de l'utilisateur
            if (!$companySettings->getEmail()) {
                $user = $this->getUser();
                if ($user && method_exists($user, 'getEmail')) {
                    $companySettings->setEmail($user->getEmail());
                }
            }
        }

        $form = $this->createForm(CompanySettingsType::class, $companySettings);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Gérer l'upload du logo
                $logoFile = $form->get('logo')->getData();
                if ($logoFile) {
                    $logoPath = $this->handleLogoUpload($logoFile, $companySettings);
                    if ($logoPath) {
                        $companySettings->setLogoPath($logoPath);
                    }
                }

                // Mappage manuel des champs secrets (mapped => false)
                $secretFields = [
                    'stripeSecretKey' => 'setStripeSecretKey',
                    'stripeWebhookSecret' => 'setStripeWebhookSecret',
                    'googleApiKey' => 'setGoogleApiKey',
                    'googleClientSecret' => 'setGoogleClientSecret',
                    'signatureApiKey' => 'setSignatureApiKey',
                    'pdpApiKey' => 'setPdpApiKey'
                ];

                foreach ($secretFields as $fieldName => $setter) {
                    $value = $form->get($fieldName)->getData();
                    if ($value !== null && $value !== '') {
                        $companySettings->$setter($value);
                    }
                }

                $this->em->persist($companySettings);
                $this->em->flush();

                $this->addFlash('success', 'Les paramètres de l\'entreprise ont été mis à jour avec succès.');

                return $this->redirectToRoute('admin_company_settings');
            } else {
                // Afficher les erreurs de validation
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('error', 'Erreurs de validation : ' . implode(', ', $errors));
                }
            }
        }

        return $this->render('admin/company/settings.html.twig', [
            'form' => $form,
            'companySettings' => $companySettings,
        ]);
    }

    /**
     * Gère l'upload du logo
     */
    private function handleLogoUpload($file, CompanySettings $companySettings): ?string
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');

        // Supprimer l'ancien logo si il existe
        if ($companySettings->getLogoPath()) {
            $oldLogoPath = $projectDir . '/public' . $companySettings->getLogoPath();
            if (file_exists($oldLogoPath)) {
                @unlink($oldLogoPath);
            }
        }

        // Générer un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        if (empty($safeFilename)) {
            $safeFilename = 'logo';
        }
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Déplacer le fichier vers le répertoire public/uploads/logos
        $uploadDir = $projectDir . '/public/uploads/logos';
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
            return '/uploads/logos/' . $newFilename;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload du logo : ' . $e->getMessage());
            return null;
        }
    }
}
