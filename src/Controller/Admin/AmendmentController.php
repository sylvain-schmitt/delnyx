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
use App\Service\AmendmentService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin/amendment', name: 'admin_amendment_')]
#[IsGranted('ROLE_USER')]
class AmendmentController extends AbstractController
{
    public function __construct(
        private AmendmentRepository $amendmentRepository,
        private QuoteRepository $quoteRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private EntityManagerInterface $entityManager,
        private AmendmentService $amendmentService,
        private \App\Service\PdfGeneratorService $pdfGeneratorService,
        private EmailService $emailService
    ) {}

    /**
     * Endpoint API pour récupérer les informations d'un devis (pour afficher les lignes en lecture seule)
     */
    #[Route('/api/quote/{id}', name: 'api_quote_info', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getQuoteInfo(int $id): JsonResponse
    {
        $quote = $this->quoteRepository->find($id);
        if (!$quote) {
            return new JsonResponse(['error' => 'Devis non trouvé'], 404);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $quote->getCompanyId() !== $companyId) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        // Vérifier que le devis est signé
        if ($quote->getStatut() !== QuoteStatus::SIGNED) {
            return new JsonResponse(['error' => 'Seuls les devis signés peuvent être utilisés'], 400);
        }

        // Récupérer CompanySettings pour savoir si TVA est activée
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }
        $isTvaEnabled = $companySettings && $companySettings->isTvaEnabled();

        // Préparer les lignes du devis avec calcul des montants TTC
        $lines = [];
        foreach ($quote->getLines() as $line) {
            $totalHt = (float)$line->getTotalHt();

            // Utiliser la méthode getTotalTtc() de QuoteLine qui gère correctement usePerLineTva
            $totalTtc = (float)$line->getTotalTtc();

            // Calculer la TVA à partir du TTC et du HT
            $tvaAmount = $totalTtc - $totalHt;

            // Déterminer le taux de TVA utilisé
            $tvaRate = null;
            if ($quote->isUsePerLineTva()) {
                $tvaRate = $line->getTvaRate();
            } else {
                $tvaRate = $quote->getTauxTVA();
            }

            $lines[] = [
                'id' => $line->getId(),
                'label' => sprintf(
                    '%s - %s × %s € = %s € HT',
                    $line->getDescription(),
                    $line->getQuantity(),
                    number_format((float)$line->getUnitPrice(), 2, ',', ' '),
                    number_format($totalHt, 2, ',', ' ')
                ),
                'description' => $line->getDescription(),
                'quantity' => $line->getQuantity(),
                'unitPrice' => $line->getUnitPrice(),
                'totalHt' => $totalHt,
                'tvaRate' => $tvaRate,
                'tvaAmount' => $tvaAmount,
                'totalTtc' => $totalTtc,
                'tariffId' => $line->getTariff()?->getId(),
            ];
        }

        return new JsonResponse([
            'id' => $quote->getId(),
            'numero' => $quote->getNumero(),
            'lines' => $lines,
            'montantTTC' => $quote->getMontantTTC(),
            'montantTTCFormate' => $quote->getMontantTTCFormate(),
            'usePerLineTva' => $quote->isUsePerLineTva(),
            'isTvaEnabled' => $isTvaEnabled,
        ]);
    }

    #[Route('/api/quote/{id}/lines', name: 'api_quote_lines', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getQuoteLines(int $id): JsonResponse
    {
        $quote = $this->quoteRepository->find($id);
        if (!$quote) {
            return new JsonResponse(['error' => 'Devis non trouvé'], 404);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $quote->getCompanyId() !== $companyId) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $lines = [];
        foreach ($quote->getLines() as $line) {
            $lines[] = [
                'id' => $line->getId(),
                'label' => sprintf(
                    '%s - %s × %s € = %s € HT',
                    $line->getDescription(),
                    $line->getQuantity(),
                    number_format((float)$line->getUnitPrice(), 2, ',', ' '),
                    number_format((float)$line->getTotalHt(), 2, ',', ' ')
                ),
                'description' => $line->getDescription(),
                'quantity' => $line->getQuantity(),
                'unitPrice' => $line->getUnitPrice(),
                'totalHt' => $line->getTotalHt(),
            ];
        }

        return new JsonResponse($lines);
    }

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
            ->leftJoin('a.quote', 'q')
            ->addSelect('q')
            ->leftJoin('q.client', 'c')
            ->addSelect('c')
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

        // Récupérer CompanySettings pour l'affichage
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }

        return $this->render('admin/amendment/show.html.twig', [
            'amendment' => $amendment,
            'companySettings' => $companySettings,
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
        $quoteId = $request->query->getInt('quote_id') ?: $request->query->get('quote_id');

        // Si pas d'ID depuis la requête mais que l'avenant a déjà un devis, utiliser son ID
        if (!$quoteId && $amendment->getQuote()) {
            $quoteId = $amendment->getQuote()->getId();
        }

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

                // Pré-remplir l'avenant avec le devis
                $amendment->setQuote($quote);
                $amendment->setTauxTVA($quote->getTauxTVA());
                // Définir des valeurs par défaut pour les champs obligatoires
                $amendment->setMotif('Modification du devis ' . $quote->getNumero());
                $amendment->setModifications('Avenant en cours de création');
            }
        }

        $form = $this->createForm(AmendmentType::class, $amendment, [
            'company_settings' => $companySettings,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // S'assurer que le devis est bien associé si sélectionné dans le formulaire
            // Récupérer depuis le formulaire d'abord
            $quoteData = $form->get('quote')->getData();

            // Si le champ est désactivé, Symfony ne le traite pas, récupérer depuis la requête
            if (!$quoteData) {
                $quoteIdFromRequest = $request->request->get('amendment')['quote'] ?? null;
                if ($quoteIdFromRequest) {
                    $quoteData = $this->quoteRepository->find($quoteIdFromRequest);
                    if ($quoteData) {
                        $amendment->setQuote($quoteData);
                    }
                }
            }

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

                        // S'assurer que oldValue est défini avant de recalculer
                        if ($line->getSourceLine() && (!$line->getOldValue() || $line->getOldValue() === '0.00')) {
                            $oldValue = (float) $line->getSourceLine()->getTotalHt();
                            $line->setOldValue(number_format($oldValue, 2, '.', ''));
                        }

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
            'quote_id' => $quoteId, // Passer l'ID directement pour le champ hidden
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

                    // S'assurer que oldValue est défini avant de recalculer
                    if ($line->getSourceLine() && (!$line->getOldValue() || $line->getOldValue() === '0.00')) {
                        $oldValue = (float) $line->getSourceLine()->getTotalHt();
                        $line->setOldValue(number_format($oldValue, 2, '.', ''));
                    }

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

    #[Route('/{id}/issue', name: 'issue', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('AMENDMENT_ISSUE', subject: 'amendment')]
    public function issue(Request $request, Amendment $amendment): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('amendment_issue_' . $amendment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

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

        try {
            $this->amendmentService->issue($amendment);
            $this->addFlash('success', 'Avenant émis avec succès.');
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
    }

    #[Route('/{id}/send', name: 'send', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('AMENDMENT_SEND', subject: 'amendment')]
    public function send(Request $request, Amendment $amendment): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('amendment_send_' . $amendment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

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

        try {
            $this->amendmentService->send($amendment);
            $this->addFlash('success', 'Avenant envoyé au client');
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
    }

    #[Route('/{id}/sign', name: 'sign', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('AMENDMENT_SIGN', subject: 'amendment')]
    public function sign(Request $request, Amendment $amendment): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('amendment_sign_' . $amendment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

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

        try {
            $signature = $request->request->get('signature');
            $this->amendmentService->sign($amendment, $signature);
            $this->addFlash('success', 'Avenant signé avec succès');
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
    }

    #[Route('/{id}/cancel', name: 'cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('AMENDMENT_CANCEL', subject: 'amendment')]
    public function cancel(Request $request, Amendment $amendment): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('amendment_cancel_' . $amendment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

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

        try {
            $reason = $request->request->get('reason');
            $this->amendmentService->cancel($amendment, $reason);
            $this->addFlash('success', 'Avenant annulé');
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
    }

    /**
     * Génère et affiche le PDF de l'avenant dans le navigateur
     */
    #[Route('/{id}/pdf', name: 'pdf', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function pdf(Amendment $amendment): Response
    {
        try {
            return $this->pdfGeneratorService->generateAvenantPdf($amendment, false);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF : ' . $e->getMessage());
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }
    }

    /**
     * Télécharge le PDF de l'avenant (génère et sauvegarde si nécessaire)
     */
    #[Route('/{id}/download-pdf', name: 'download_pdf', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function downloadPdf(Amendment $amendment): Response
    {
        try {
            // Si le PDF n'a pas encore été généré, le générer et sauvegarder
            if (!$amendment->getPdfFilename()) {
                $result = $this->pdfGeneratorService->generateAvenantPdf($amendment, true);
                
                // Sauvegarder le nom de fichier et le hash dans l'entité
                $amendment->setPdfFilename($result['filename']);
                $amendment->setPdfHash($result['hash']);
                $this->entityManager->flush();
                
                // Retourner la réponse PDF
                return $result['response'];
            }

            // Si le PDF existe déjà, le retourner depuis le fichier sauvegardé
            $filePath = $this->getParameter('kernel.project_dir') . '/var/generated_pdfs/' . $amendment->getPdfFilename();
            
            if (!file_exists($filePath)) {
                // Le fichier n'existe plus, régénérer
                $result = $this->pdfGeneratorService->generateAvenantPdf($amendment, true);
                $amendment->setPdfFilename($result['filename']);
                $amendment->setPdfHash($result['hash']);
                $this->entityManager->flush();
                
                return $result['response'];
            }

            // Retourner le fichier existant
            return $this->file($filePath, 'avenant-' . ($amendment->getNumero() ?? $amendment->getId()) . '.pdf', ResponseHeaderBag::DISPOSITION_INLINE);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du téléchargement du PDF : ' . $e->getMessage());
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }
    }

    /**
     * Envoie l'avenant par email
     */
    #[Route('/{id}/send-email', name: 'send_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendEmail(Request $request, Amendment $amendment): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('amendment_send_email_' . $amendment->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

        // Vérifier que l'avenant a un client avec un email
        $quote = $amendment->getQuote();
        $client = $quote?->getClient();
        
        if (!$client || !$client->getEmail()) {
            $this->addFlash('error', 'Impossible d\'envoyer l\'avenant : aucun email client configuré.');
            return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
        }

        try {
            $customMessage = $request->request->get('custom_message');
            $emailLog = $this->emailService->sendAmendment($amendment, $customMessage);
            
            if ($emailLog->getStatus() === 'sent') {
                $this->addFlash('success', sprintf('Avenant envoyé avec succès à %s', $client->getEmail()));
            } else {
                $this->addFlash('error', sprintf('Erreur lors de l\'envoi : %s', $emailLog->getErrorMessage()));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_amendment_show', ['id' => $amendment->getId()]);
    }
}
