<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Form\AmendmentType;
use App\Repository\AmendmentRepository;
use App\Repository\QuoteRepository;
use App\Repository\CompanySettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/amendment', name: 'admin_amendment_')]
#[IsGranted('ROLE_USER')]
class AmendmentController extends AbstractController
{
    public function __construct(
        private AmendmentRepository $amendmentRepository,
        private QuoteRepository $quoteRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 15;
        $includeCancelled = $request->query->getBoolean('include_cancelled', false);

        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        $qb = $this->amendmentRepository->createQueryBuilder('a');

        if ($companyId) {
            $qb->where('a.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        // Filtrer par devis si fourni
        $quoteId = $request->query->getInt('quote_id');
        if ($quoteId) {
            $quote = $this->quoteRepository->find($quoteId);
            if ($quote) {
                // Vérifier le multi-tenant
                if (!$companyId || $quote->getCompanyId() === $companyId) {
                    if ($companyId) {
                        $qb->andWhere('a.quote = :quote')
                           ->setParameter('quote', $quote);
                    } else {
                        $qb->where('a.quote = :quote')
                           ->setParameter('quote', $quote);
                    }
                }
            }
        }

        if (!$includeCancelled) {
            if ($qb->getDQLPart('where')) {
                $qb->andWhere('a.statut != :cancelled');
            } else {
                $qb->where('a.statut != :cancelled');
            }
            $qb->setParameter('cancelled', AmendmentStatus::CANCELLED);
        }

        $totalAmendments = (int) $qb->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = (int) ceil($totalAmendments / $limit);
        $page = min($page, max(1, $totalPages));

        $amendments = $qb->select('a')
            ->orderBy('a.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit)
            ->getQuery()
            ->getResult();

        $filteredQuote = null;
        if ($quoteId) {
            $filteredQuote = $this->quoteRepository->find($quoteId);
        }

        return $this->render('admin/amendment/index.html.twig', [
            'amendments' => $amendments,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_amendments' => $totalAmendments,
            'include_cancelled' => $includeCancelled,
            'filtered_quote' => $filteredQuote,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Amendment $amendment): Response
    {
        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }
        
        if ($companyId && $amendment->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avenant.');
            return $this->redirectToRoute('admin_amendment_index');
        }

        return $this->render('admin/amendment/show.html.twig', [
            'amendment' => $amendment,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $amendment = new Amendment();

        // Pré-remplir le company_id
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
            $amendment->setCompanyId($companyId);
        }

        // Récupérer CompanySettings
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }

        // Pré-remplir depuis un devis si fourni en paramètre
        $quoteId = $request->query->get('quote_id');
        if ($quoteId) {
            $quote = $this->quoteRepository->find($quoteId);
            if ($quote) {
                // Vérifier le multi-tenant
                if ($companyId && $quote->getCompanyId() !== $companyId) {
                    $this->addFlash('error', 'Vous n\'avez pas accès à ce devis.');
                    return $this->redirectToRoute('admin_amendment_index');
                }

                // Vérifier que le devis est signé
                if ($quote->getStatut() !== QuoteStatus::SIGNED) {
                    $this->addFlash('error', 'Un avenant ne peut être créé que pour un devis signé.');
                    return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
                }

                $amendment->setQuote($quote);
                $amendment->setTauxTVA($quote->getTauxTVA());
                
                // IMPORTANT : Ne PAS copier les lignes du devis
                // Les lignes d'avenant sont des AJUSTEMENTS (deltas) uniquement
                // Les lignes du devis restent figées et seront affichées en lecture seule dans le formulaire
            }
        }

        $form = $this->createForm(AmendmentType::class, $amendment, [
            'company_settings' => $companySettings,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier que le devis est signé
            if (!$amendment->getQuote()) {
                $this->addFlash('error', 'Un avenant doit être lié à un devis.');
            } elseif ($amendment->getQuote()->getStatut() !== QuoteStatus::SIGNED) {
                $this->addFlash('error', 'Un avenant ne peut être créé que pour un devis signé.');
            } else {
                // Vérifier qu'au moins une ligne est présente
                if ($amendment->getLines()->isEmpty()) {
                    $this->addFlash('error', 'Au moins une ligne d\'avenant est requise.');
                } else {
                    // Associer les lignes à l'avenant
                    foreach ($amendment->getLines() as $line) {
                        $line->setAmendment($amendment);
                        $line->recalculateTotalHt();
                    }

                    // Recalculer les totaux
                    $amendment->recalculateTotalsFromLines();

                    $this->entityManager->persist($amendment);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Avenant créé avec succès');
                    return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
                }
            }
        }

        return $this->render('admin/amendment/form.html.twig', [
            'amendment' => $amendment,
            'form' => $form,
            'title' => 'Nouvel Avenant',
            'companySettings' => $companySettings,
            'quote_locked' => $quoteId !== null, // Verrouiller le champ si on vient d'un devis
            'quote' => $amendment->getQuote(), // Passer le devis pour afficher ses lignes
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Amendment $amendment): Response
    {
        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }
        
        if ($companyId && $amendment->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avenant.');
            return $this->redirectToRoute('admin_amendment_index');
        }

        // Vérifier si l'avenant peut être modifié
        if (!$amendment->canBeModified()) {
            $this->addFlash('error', 'Cet avenant ne peut plus être modifié car il est ' . $amendment->getStatutLabel() . '.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

        // Récupérer CompanySettings
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }

        $form = $this->createForm(AmendmentType::class, $amendment, [
            'company_settings' => $companySettings,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier qu'au moins une ligne est présente
            if ($amendment->getLines()->isEmpty()) {
                $this->addFlash('error', 'Au moins une ligne d\'avenant est requise.');
            } else {
                // Associer les lignes à l'avenant
                foreach ($amendment->getLines() as $line) {
                    $line->setAmendment($amendment);
                    $line->recalculateTotalHt();
                }

                // Recalculer les totaux
                $amendment->recalculateTotalsFromLines();
                $amendment->setDateModification(new \DateTime());

                $this->entityManager->flush();

                $this->addFlash('success', 'Avenant modifié avec succès');
                return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
            }
        }

        return $this->render('admin/amendment/form.html.twig', [
            'amendment' => $amendment,
            'form' => $form,
            'title' => 'Modifier l\'Avenant ' . ($amendment->getNumero() ?? ''),
            'companySettings' => $companySettings,
            'quote' => $amendment->getQuote(), // Passer le devis pour afficher ses lignes
        ]);
    }

    #[Route('/{id}/send', name: 'send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function send(Amendment $amendment): Response
    {
        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }
        
        if ($companyId && $amendment->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avenant.');
            return $this->redirectToRoute('admin_amendment_index');
        }

        if ($amendment->getStatut() !== AmendmentStatus::DRAFT) {
            $this->addFlash('error', 'Seuls les avenants en brouillon peuvent être envoyés.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

        $amendment->setStatut(AmendmentStatus::SENT);
        $this->entityManager->flush();

        $this->addFlash('success', 'Avenant envoyé au client');
        return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
    }

    #[Route('/{id}/sign', name: 'sign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sign(Request $request, Amendment $amendment): Response
    {
        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }
        
        if ($companyId && $amendment->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avenant.');
            return $this->redirectToRoute('admin_amendment_index');
        }

        if (!$amendment->canBeSigned()) {
            $this->addFlash('error', 'Cet avenant ne peut pas être signé dans son état actuel.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

        try {
            $amendment->validateCanBeSigned();
            
            $signature = $request->request->get('signature');
            if ($signature) {
                $amendment->setSignatureClient($signature);
            }
            
            $amendment->setStatut(AmendmentStatus::SIGNED);
            $amendment->setDateSignature(new \DateTime());
            
            $this->entityManager->flush();

            $this->addFlash('success', 'Avenant signé avec succès');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
    }

    #[Route('/{id}/cancel', name: 'cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Amendment $amendment): Response
    {
        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }
        
        if ($companyId && $amendment->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avenant.');
            return $this->redirectToRoute('admin_amendment_index');
        }

        if (!$amendment->canBeCancelled()) {
            $this->addFlash('error', 'Cet avenant ne peut pas être annulé.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

        $amendment->setStatut(AmendmentStatus::CANCELLED);
        $this->entityManager->flush();

        $this->addFlash('success', 'Avenant annulé');
        return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
    }
}

