<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Form\CreditNoteType;
use App\Repository\CreditNoteRepository;
use App\Repository\InvoiceRepository;
use App\Repository\CompanySettingsRepository;
use App\Repository\TariffRepository;
use App\Service\CreditNoteService;
use App\Service\PdfGeneratorService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin/credit-note', name: 'admin_credit_note_')]
#[IsGranted('ROLE_USER')]
class CreditNoteController extends AbstractController
{
    public function __construct(
        private CreditNoteRepository $creditNoteRepository,
        private InvoiceRepository $invoiceRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private TariffRepository $tariffRepository,
        private EntityManagerInterface $entityManager,
        private CreditNoteService $creditNoteService,
        private PdfGeneratorService $pdfGeneratorService,
        private EmailService $emailService
    ) {}

    #[Route('/api/invoice/{id}', name: 'api_invoice_info', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getInvoiceInfo(int $id): JsonResponse
    {
        // Charger la facture avec ses lignes (eager loading)
        // Utiliser une requête DQL explicite pour forcer le chargement des lignes
        $invoice = $this->invoiceRepository->createQueryBuilder('i')
            ->leftJoin('i.lines', 'l')
            ->addSelect('l')
            ->where('i.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$invoice) {
            return new JsonResponse(['error' => 'Facture non trouvée'], 404);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $invoice->getCompanyId() !== $companyId) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }


        // Charger explicitement les lignes avec une requête SQL directe
        // C'est la méthode la plus fiable pour tous les statuts de facture
        $invoiceId = $invoice->getId();
        $lines = [];

        // Requête SQL directe pour récupérer les lignes
        $connection = $this->entityManager->getConnection();
        $sql = 'SELECT id, description, quantity, unit_price, total_ht, tva_rate
                FROM invoice_lines
                WHERE invoice_id = :invoiceId
                ORDER BY id ASC';
        $stmt = $connection->prepare($sql);
        $result = $stmt->executeQuery(['invoiceId' => $invoiceId]);
        $rawLines = $result->fetchAllAssociative();

        // Convertir les lignes brutes en format attendu
        foreach ($rawLines as $rawLine) {
            $lines[] = [
                'id' => (int) $rawLine['id'],
                'description' => $rawLine['description'],
                'quantity' => (float) $rawLine['quantity'],
                'unitPrice' => (float) $rawLine['unit_price'],
                'totalHt' => (float) $rawLine['total_ht'],
                'tvaRate' => (float) $rawLine['tva_rate'],
            ];
        }

        return new JsonResponse([
            'id' => $invoice->getId(),
            'numero' => $invoice->getNumero(),
            'statut' => $invoice->getStatut(),
            'lines' => $lines,
            'linesCount' => count($lines),
            'montantTTCFormate' => $invoice->getMontantTTCFormate(),
        ]);
    }

    #[Route('/api/invoice/{id}/lines', name: 'api_invoice_lines', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getInvoiceLines(int $id): JsonResponse
    {
        $invoice = $this->invoiceRepository->find($id);
        if (!$invoice) {
            return new JsonResponse(['error' => 'Facture non trouvée'], 404);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $invoice->getCompanyId() !== $companyId) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $lines = [];
        foreach ($invoice->getLines() as $line) {
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

        $qb = $this->creditNoteRepository->createQueryBuilder('cn');

        if ($companyId) {
            $qb->where('cn.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        // Filtrer par facture si fournie
        $invoiceId = $request->query->getInt('invoice_id');
        if ($invoiceId) {
            $invoice = $this->invoiceRepository->find($invoiceId);
            if ($invoice) {
                // Vérifier le multi-tenant
                if (!$companyId || $invoice->getCompanyId() === $companyId) {
                    if ($companyId) {
                        $qb->andWhere('cn.invoice = :invoice')
                            ->setParameter('invoice', $invoice);
                    } else {
                        $qb->where('cn.invoice = :invoice')
                            ->setParameter('invoice', $invoice);
                    }
                }
            }
        }

        if (!$includeCancelled) {
            if ($qb->getDQLPart('where')) {
                $qb->andWhere('cn.statut != :cancelled');
            } else {
                $qb->where('cn.statut != :cancelled');
            }
            $qb->setParameter('cancelled', CreditNoteStatus::CANCELLED);
        }

        $totalCreditNotes = (int) $qb->select('COUNT(cn.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPages = (int) ceil($totalCreditNotes / $limit);
        $page = min($page, max(1, $totalPages));

        $creditNotes = $qb->select('cn')
            ->leftJoin('cn.invoice', 'i')
            ->addSelect('i')
            ->leftJoin('i.client', 'c')
            ->addSelect('c')
            ->orderBy('cn.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit)
            ->getQuery()
            ->getResult();

        $filteredInvoice = null;
        if ($invoiceId) {
            $filteredInvoice = $this->invoiceRepository->find($invoiceId);
        }

        return $this->render('admin/credit_note/index.html.twig', [
            'creditNotes' => $creditNotes,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_credit_notes' => $totalCreditNotes,
            'include_cancelled' => $includeCancelled,
            'filtered_invoice' => $filteredInvoice,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(CreditNote $creditNote): Response
    {
        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $creditNote->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avoir.');
            return $this->redirectToRoute('admin_credit_note_index');
        }

        // Récupérer CompanySettings pour l'affichage
        $companySettings = null;
        if ($creditNote->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($creditNote->getCompanyId());
        }

        return $this->render('admin/credit_note/show.html.twig', [
            'creditNote' => $creditNote,
            'companySettings' => $companySettings,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $creditNote = new CreditNote();

        // Pré-remplir le company_id
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
            $creditNote->setCompanyId($companyId);
        }

        // Récupérer CompanySettings
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }

        // Pré-remplir depuis une facture si fournie en paramètre
        $invoiceId = $request->query->getInt('invoice_id') ?: $request->query->get('invoice_id');

        // Si pas d'ID depuis la requête mais que l'avoir a déjà une facture, utiliser son ID
        if (!$invoiceId && $creditNote->getInvoice()) {
            $invoiceId = $creditNote->getInvoice()->getId();
        }

        if ($invoiceId) {
            // Charger la facture avec ses lignes pour le formulaire
            $invoice = $this->invoiceRepository->createQueryBuilder('i')
                ->leftJoin('i.lines', 'l')
                ->addSelect('l')
                ->where('i.id = :invoiceId')
                ->setParameter('invoiceId', $invoiceId)
                ->getQuery()
                ->getOneOrNullResult();

            if ($invoice) {
                // Vérifier le multi-tenant
                if ($companyId && $invoice->getCompanyId() !== $companyId) {
                    $this->addFlash('error', 'Vous n\'avez pas accès à cette facture.');
                    return $this->redirectToRoute('admin_credit_note_index');
                }

                // Vérifier que la facture est émise
                $invoiceStatut = $invoice->getStatutEnum();
                if (!$invoiceStatut || !$invoiceStatut->isEmitted()) {
                    $this->addFlash('error', 'Un avoir ne peut être créé que pour une facture émise.');
                    return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
                }

                // Vérifier que la facture n'est pas annulée
                if ($invoiceStatut === \App\Entity\InvoiceStatus::CANCELLED) {
                    $this->addFlash('error', 'Un avoir ne peut pas être créé pour une facture annulée.');
                    return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
                }

                $creditNote->setInvoice($invoice);
                // Pré-remplir le motif avec une valeur par défaut
                if (!$creditNote->getReason()) {
                    $creditNote->setReason('Avoir pour la facture ' . $invoice->getNumero());
                }
                // NE PAS pré-remplir les lignes - l'utilisateur doit créer les lignes d'ajustement manuellement
            }
        }

        $form = $this->createForm(CreditNoteType::class, $creditNote, [
            'company_settings' => $companySettings,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // S'assurer que la facture est bien associée si sélectionnée dans le formulaire
            // Récupérer depuis le formulaire d'abord
            $invoiceData = $form->get('invoice')->getData();

            // Si le champ est désactivé, Symfony ne le traite pas, récupérer depuis la requête
            if (!$invoiceData) {
                $invoiceIdFromRequest = $request->request->get('credit_note')['invoice'] ?? null;
                if ($invoiceIdFromRequest) {
                    $invoiceData = $this->invoiceRepository->find($invoiceIdFromRequest);
                    if ($invoiceData) {
                        $creditNote->setInvoice($invoiceData);
                    }
                }
            }

            // Vérifier que la facture est émise
            if (!$creditNote->getInvoice()) {
                $this->addFlash('error', 'Un avoir doit être lié à une facture.');
                return $this->render('admin/credit_note/form.html.twig', [
                    'creditNote' => $creditNote,
                    'form' => $form,
                    'title' => 'Nouvel Avoir',
                    'companySettings' => $companySettings,
                    'invoice_locked' => $invoiceId !== null,
                    'invoice_id' => $invoiceId,
                ], new \Symfony\Component\HttpFoundation\Response(null, 422));
            }

            $invoiceStatut = $creditNote->getInvoice()->getStatutEnum();
            if (!$invoiceStatut || !$invoiceStatut->isEmitted()) {
                $this->addFlash('error', 'Un avoir ne peut être créé que pour une facture émise.');
                return $this->render('admin/credit_note/form.html.twig', [
                    'creditNote' => $creditNote,
                    'form' => $form,
                    'title' => 'Nouvel Avoir',
                    'companySettings' => $companySettings,
                    'invoice_locked' => $invoiceId !== null,
                    'invoice_id' => $invoiceId,
                ], new \Symfony\Component\HttpFoundation\Response(null, 422));
            }

            // Vérifier que la facture n'est pas annulée
            if ($invoiceStatut === \App\Entity\InvoiceStatus::CANCELLED) {
                $this->addFlash('error', 'Un avoir ne peut pas être créé pour une facture annulée.');
                return $this->render('admin/credit_note/form.html.twig', [
                    'creditNote' => $creditNote,
                    'form' => $form,
                    'title' => 'Nouvel Avoir',
                    'companySettings' => $companySettings,
                    'invoice_locked' => $invoiceId !== null,
                    'invoice_id' => $invoiceId,
                ], new \Symfony\Component\HttpFoundation\Response(null, 422));
            }

            // Vérifier qu'au moins une ligne est présente
            if ($creditNote->getLines()->isEmpty()) {
                // $this->addFlash('error', 'Au moins une ligne d\'avoir est requise.');
                return $this->render('admin/credit_note/form.html.twig', [
                    'creditNote' => $creditNote,
                    'form' => $form,
                    'title' => 'Nouvel Avoir',
                    'companySettings' => $companySettings,
                    'invoice_locked' => $invoiceId !== null,
                    'invoice_id' => $invoiceId,
                ], new \Symfony\Component\HttpFoundation\Response(null, 422));
            }

            // Associer les lignes à l'avoir
            foreach ($creditNote->getLines() as $line) {
                $line->setCreditNote($creditNote);
                $line->recalculateTotalHt();
            }

            // Recalculer les totaux
            $creditNote->recalculateTotals();

            // Vérifier que le total des avoirs ne dépasse pas le montant de la facture
            try {
                $creditNote->validateCanBeIssued();
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->render('admin/credit_note/form.html.twig', [
                    'creditNote' => $creditNote,
                    'form' => $form,
                    'title' => 'Nouvel Avoir',
                    'companySettings' => $companySettings,
                    'invoice_locked' => $invoiceId !== null,
                    'invoice_id' => $invoiceId,
                ], new \Symfony\Component\HttpFoundation\Response(null, 422));
            }

            // Sauvegarder le statut de la facture avant création pour vérifier si elle a été annulée
            $invoice = $creditNote->getInvoice();
            $invoiceStatutAvant = $invoice ? $invoice->getStatutEnum() : null;

            // Vérifier si le statut est "émis" (ISSUED) - émission automatique
            $statutEnum = $creditNote->getStatutEnum();
            $shouldAutoIssue = $statutEnum && $statutEnum === CreditNoteStatus::ISSUED;

            $this->entityManager->persist($creditNote);
            $this->entityManager->flush();

            // Si le statut est "émis", traiter l'émission automatique
            if ($shouldAutoIssue) {
                $invoiceId = $invoice ? $invoice->getId() : null;

                // Recharger la facture depuis la base avec ses avoirs
                if ($invoiceId) {
                    $invoice = $this->invoiceRepository->createQueryBuilder('i')
                        ->leftJoin('i.creditNotes', 'cn')
                        ->addSelect('cn')
                        ->where('i.id = :invoiceId')
                        ->setParameter('invoiceId', $invoiceId)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if ($invoice) {
                        // Calculer le total de tous les avoirs émis
                        // Les avoirs sont stockés en montants négatifs
                        $totalAvoirsEmitted = 0.0;
                        foreach ($invoice->getCreditNotes() as $existingCreditNote) {
                            $existingStatut = $existingCreditNote->getStatutEnum();
                            if ($existingStatut && $existingStatut === CreditNoteStatus::ISSUED) {
                                $totalAvoirsEmitted += (float) $existingCreditNote->getMontantTTC();
                            }
                        }

                        $montantFactureTTC = (float) $invoice->getMontantTTC();

                        // Si les avoirs annulent complètement la facture (solde final = 0)
                        // Exemple : facture 200€ + avoir -200€ = 0€ (avoir total)
                        $soldeFinal = $montantFactureTTC + $totalAvoirsEmitted;
                        if (abs($soldeFinal) < 0.01) {
                            $invoice->setStatut(InvoiceStatus::CANCELLED->value);
                            $invoice->setDateModification(new \DateTime());
                            $this->entityManager->persist($invoice);
                            $this->entityManager->flush();

                            $this->addFlash('info', sprintf(
                                'Avoir créé et émis avec succès. La facture %s a été automatiquement annulée car le total des avoirs émis annule complètement la facture.',
                                $invoice->getNumero()
                            ));
                        } else {
                            $this->addFlash('success', 'Avoir créé et émis avec succès');
                        }
                    } else {
                        $this->addFlash('success', 'Avoir créé et émis avec succès');
                    }
                } else {
                    $this->addFlash('success', 'Avoir créé et émis avec succès');
                }
            } else {
                // Vérifier si la facture a été annulée automatiquement (avoir total)
                if ($invoice) {
                    $this->entityManager->refresh($invoice);
                    $invoiceStatutApres = $invoice->getStatutEnum();

                    if (
                        $invoiceStatutAvant !== \App\Entity\InvoiceStatus::CANCELLED &&
                        $invoiceStatutApres === \App\Entity\InvoiceStatus::CANCELLED
                    ) {
                        $this->addFlash('info', sprintf(
                            'Avoir créé avec succès. La facture %s a été automatiquement annulée car le total des avoirs émis annule complètement la facture.',
                            $invoice->getNumero()
                        ));
                    } else {
                        $this->addFlash('success', 'Avoir créé avec succès');
                    }
                } else {
                    $this->addFlash('success', 'Avoir créé avec succès');
                }
            }

            return $this->redirectToRoute('admin_credit_note_index');
        }

        // Si le formulaire est soumis mais invalide, retourner un code 422 pour Turbo
        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->render('admin/credit_note/form.html.twig', [
                'creditNote' => $creditNote,
                'form' => $form,
                'title' => 'Nouvel Avoir',
                'companySettings' => $companySettings,
                'invoice_locked' => $invoiceId !== null,
                'invoice_id' => $invoiceId,
            ], new \Symfony\Component\HttpFoundation\Response(null, 422));
        }

        // S'assurer que la facture est bien chargée avec ses lignes si elle existe
        $invoice = $creditNote->getInvoice();
        if ($invoice && !$invoice->getLines()->isInitialized()) {
            // Recharger la facture avec ses lignes si elles ne sont pas initialisées
            $invoice = $this->invoiceRepository->createQueryBuilder('i')
                ->leftJoin('i.lines', 'l')
                ->addSelect('l')
                ->where('i.id = :id')
                ->setParameter('id', $invoice->getId())
                ->getQuery()
                ->getOneOrNullResult();
            if ($invoice) {
                $creditNote->setInvoice($invoice);
            }
        }

        return $this->render('admin/credit_note/form.html.twig', [
            'creditNote' => $creditNote,
            'form' => $form,
            'title' => 'Nouvel Avoir',
            'companySettings' => $companySettings,
            'invoice_locked' => $invoiceId !== null, // Verrouiller le champ si on vient d'une facture
            'invoice_id' => $invoiceId, // Passer l'ID directement pour le champ hidden
            'invoice' => $creditNote->getInvoice(), // Passer la facture pour afficher ses lignes en lecture seule
            'tariffs' => $this->tariffRepository->findBy(['actif' => true], ['ordre' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, CreditNote $creditNote): Response
    {
        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $creditNote->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avoir.');
            return $this->redirectToRoute('admin_credit_note_index');
        }

        // Vérifier si l'avoir peut être modifié
        if (!$creditNote->canBeModified()) {
            $this->addFlash('error', 'Cet avoir ne peut plus être modifié car il est ' . $creditNote->getStatutLabel() . '.');
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Récupérer CompanySettings
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }

        $form = $this->createForm(CreditNoteType::class, $creditNote, [
            'company_settings' => $companySettings,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier qu'au moins une ligne est présente
            if ($creditNote->getLines()->isEmpty()) {
                $this->addFlash('error', 'Au moins une ligne d\'avoir est requise.');
                return $this->render('admin/credit_note/form.html.twig', [
                    'creditNote' => $creditNote,
                    'form' => $form,
                    'title' => 'Modifier l\'Avoir ' . ($creditNote->getNumber() ?? ''),
                    'companySettings' => $companySettings,
                ], new \Symfony\Component\HttpFoundation\Response(null, 422));
            }

            // Associer les lignes à l'avoir
            foreach ($creditNote->getLines() as $line) {
                $line->setCreditNote($creditNote);
                $line->recalculateTotalHt();
            }

            // Recalculer les totaux
            $creditNote->recalculateTotals();
            $creditNote->setDateModification(new \DateTime());

            // Vérifier si le statut est "émis" (ISSUED) - émission automatique
            $statutEnum = $creditNote->getStatutEnum();
            $shouldAutoIssue = $statutEnum && $statutEnum === CreditNoteStatus::ISSUED;

            // Si le statut passe à "émis", définir la date d'émission
            if ($shouldAutoIssue && !$creditNote->getDateEmission()) {
                $creditNote->setDateEmission(new \DateTime());
            }

            // Vérifier que le total des avoirs ne dépasse pas le montant de la facture
            try {
                $creditNote->validateCanBeIssued();
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->render('admin/credit_note/form.html.twig', [
                    'creditNote' => $creditNote,
                    'form' => $form,
                    'title' => 'Modifier l\'Avoir ' . ($creditNote->getNumber() ?? ''),
                    'companySettings' => $companySettings,
                ], new \Symfony\Component\HttpFoundation\Response(null, 422));
            }

            $this->entityManager->flush();

            // Si le statut est "émis", traiter l'émission automatique
            if ($shouldAutoIssue) {
                $invoice = $creditNote->getInvoice();
                $invoiceId = $invoice ? $invoice->getId() : null;

                // Recharger la facture depuis la base avec ses avoirs
                if ($invoiceId) {
                    $invoice = $this->invoiceRepository->createQueryBuilder('i')
                        ->leftJoin('i.creditNotes', 'cn')
                        ->addSelect('cn')
                        ->where('i.id = :invoiceId')
                        ->setParameter('invoiceId', $invoiceId)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if ($invoice) {
                        // Calculer le total de tous les avoirs émis
                        $totalAvoirsEmitted = 0.0;
                        foreach ($invoice->getCreditNotes() as $existingCreditNote) {
                            $existingStatut = $existingCreditNote->getStatutEnum();
                            if ($existingStatut && $existingStatut === CreditNoteStatus::ISSUED) {
                                $totalAvoirsEmitted += (float) $existingCreditNote->getMontantTTC();
                            }
                        }

                        $montantFactureTTC = (float) $invoice->getMontantTTC();

                        // Si le total des avoirs émis = montant facture, annuler la facture
                        if (abs($totalAvoirsEmitted - $montantFactureTTC) < 0.01) {
                            $invoice->setStatut(InvoiceStatus::CANCELLED->value);
                            $invoice->setDateModification(new \DateTime());
                            $this->entityManager->persist($invoice);
                            $this->entityManager->flush();

                            $this->addFlash('info', sprintf(
                                'Avoir modifié et émis avec succès. La facture %s a été automatiquement annulée car le total des avoirs émis annule complètement la facture.',
                                $invoice->getNumero()
                            ));
                        } else {
                            $this->addFlash('success', 'Avoir modifié et émis avec succès');
                        }
                    } else {
                        $this->addFlash('success', 'Avoir modifié et émis avec succès');
                    }
                } else {
                    $this->addFlash('success', 'Avoir modifié et émis avec succès');
                }
            } else {
                $this->addFlash('success', 'Avoir modifié avec succès');
            }

            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Si le formulaire est soumis mais invalide, retourner un code 422 pour Turbo
        if ($form->isSubmitted() && !$form->isValid()) {
            return $this->render('admin/credit_note/form.html.twig', [
                'creditNote' => $creditNote,
                'form' => $form,
                'title' => 'Modifier l\'Avoir ' . ($creditNote->getNumber() ?? ''),
                'companySettings' => $companySettings,
            ], new \Symfony\Component\HttpFoundation\Response(null, 422));
        }

        // Récupérer l'ID de la facture si elle existe
        $invoiceId = $creditNote->getInvoice() ? $creditNote->getInvoice()->getId() : null;

        return $this->render('admin/credit_note/form.html.twig', [
            'creditNote' => $creditNote,
            'form' => $form,
            'title' => 'Modifier l\'Avoir ' . ($creditNote->getNumber() ?? ''),
            'companySettings' => $companySettings,
            'invoice_locked' => false,
            'invoice_id' => $invoiceId,
            'invoice' => $creditNote->getInvoice(), // Passer la facture pour afficher ses lignes en lecture seule
            'tariffs' => $this->tariffRepository->findBy(['actif' => true], ['ordre' => 'ASC', 'nom' => 'ASC']),
        ]);
    }

    #[Route('/{id}/issue-and-send', name: 'issue_and_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('CREDIT_NOTE_ISSUE', subject: 'creditNote')]
    public function issueAndSend(Request $request, CreditNote $creditNote): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('credit_note_issue_and_send_' . $creditNote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $creditNote->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avoir.');
            return $this->redirectToRoute('admin_credit_note_index');
        }

        try {
            $this->creditNoteService->issueAndSend($creditNote);

            // FIX: Envoyer l'email réellement via EmailService
            $invoice = $creditNote->getInvoice();
            $client = $invoice ? $invoice->getClient() : null;
            if ($client && $client->getEmail()) {
                $customMessage = $request->request->get('custom_message');
                $uploadedFiles = $request->files->get('attachments', []);
                $this->emailService->sendCreditNote($creditNote, $customMessage, $uploadedFiles);
            }

            // Vérifier si la facture doit être annulée (avoir total)
            if ($invoice) {
                $this->entityManager->refresh($invoice);
                if ($invoice->getStatutEnum() === InvoiceStatus::CANCELLED) {
                    $this->addFlash('info', sprintf(
                        'Avoir émis et envoyé avec succès. La facture %s a été automatiquement annulée car le total des avoirs émis annule complètement la facture.',
                        $invoice->getNumero()
                    ));
                } else {
                    $this->addFlash('success', 'Avoir émis et envoyé avec succès');
                }
            } else {
                $this->addFlash('success', 'Avoir émis et envoyé avec succès');
            }
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
    }

    #[Route('/{id}/issue', name: 'issue', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('CREDIT_NOTE_ISSUE', subject: 'creditNote')]
    public function issue(Request $request, CreditNote $creditNote): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('credit_note_issue_' . $creditNote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $creditNote->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avoir.');
            return $this->redirectToRoute('admin_credit_note_index');
        }

        try {
            $this->creditNoteService->issue($creditNote);

            // Vérifier si la facture doit être annulée (avoir total)
            $invoice = $creditNote->getInvoice();
            if ($invoice) {
                $this->entityManager->refresh($invoice);
                if ($invoice->getStatutEnum() === InvoiceStatus::CANCELLED) {
                    $this->addFlash('info', sprintf(
                        'Avoir émis avec succès. La facture %s a été automatiquement annulée car le total des avoirs émis annule complètement la facture.',
                        $invoice->getNumero()
                    ));
                } else {
                    $this->addFlash('success', 'Avoir émis avec succès');
                }
            } else {
                $this->addFlash('success', 'Avoir émis avec succès');
            }
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
    }

    #[Route('/{id}/send', name: 'send', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('CREDIT_NOTE_SEND', subject: 'creditNote')]
    public function send(Request $request, CreditNote $creditNote): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('credit_note_send_' . $creditNote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $creditNote->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avoir.');
            return $this->redirectToRoute('admin_credit_note_index');
        }

        try {
            $this->creditNoteService->send($creditNote);

            // FIX: Envoyer l'email réellement via EmailService
            $invoice = $creditNote->getInvoice();
            $client = $invoice ? $invoice->getClient() : null;
            if ($client && $client->getEmail()) {
                $customMessage = $request->request->get('custom_message');
                $uploadedFiles = $request->files->get('attachments', []);
                $this->emailService->sendCreditNote($creditNote, $customMessage, $uploadedFiles);
            }

            $this->addFlash('success', 'Avoir envoyé au client');
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
    }

    #[Route('/{id}/apply', name: 'apply', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('CREDIT_NOTE_APPLY', subject: 'creditNote')]
    public function apply(Request $request, CreditNote $creditNote): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('credit_note_apply_' . $creditNote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $creditNote->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avoir.');
            return $this->redirectToRoute('admin_credit_note_index');
        }

        try {
            $this->creditNoteService->apply($creditNote);
            $this->addFlash('success', 'Avoir appliqué avec succès');
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
    }

    #[Route('/{id}/cancel', name: 'cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('CREDIT_NOTE_CANCEL', subject: 'creditNote')]
    public function cancel(Request $request, CreditNote $creditNote): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('credit_note_cancel_' . $creditNote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Vérifier le multi-tenant
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }

        if ($companyId && $creditNote->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cet avoir.');
            return $this->redirectToRoute('admin_credit_note_index');
        }

        try {
            $reason = $request->request->get('reason');
            $this->creditNoteService->cancel($creditNote, $reason);
            $this->addFlash('success', 'Avoir annulé');
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
    }

    /**
     * Télécharge le PDF de l'avoir (génère et sauvegarde si nécessaire)
     */
    #[Route('/{id}/download-pdf', name: 'download_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadPdf(CreditNote $creditNote): Response
    {
        try {
            // Si le PDF n'a pas encore été généré, le générer et sauvegarder
            if (!$creditNote->getPdfFilename()) {
                $result = $this->pdfGeneratorService->generateCreditNotePdf($creditNote, true);

                // Sauvegarder le nom de fichier et le hash dans l'entité
                $creditNote->setPdfFilename($result['filename']);
                $creditNote->setPdfHash($result['hash']);
                $this->entityManager->flush();

                return new Response(
                    $result['pdf'],
                    200,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '.pdf"'
                    ]
                );
            }

            // Sinon, retourner le PDF existant
            return $this->pdfGeneratorService->generateCreditNotePdf($creditNote, false);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF : ' . $e->getMessage());
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }
    }

    /**
     * Envoie l'avoir par email
     */
    #[Route('/{id}/send-email', name: 'send_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendEmail(Request $request, CreditNote $creditNote): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('credit_note_send_email_' . $creditNote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Envoyer l'avoir (gère automatiquement l'émission si DRAFT)
        try {
            $this->creditNoteService->send($creditNote);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        // Vérifier que l'avoir a un client avec un email
        $invoice = $creditNote->getInvoice();
        $client = $invoice?->getClient();

        if (!$client || !$client->getEmail()) {
            $this->addFlash('error', 'Impossible d\'envoyer l\'avoir : aucun email client configuré.');
            return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
        }

        try {
            $customMessage = $request->request->get('custom_message');

            // Récupérer les fichiers uploadés
            $uploadedFiles = $request->files->get('attachments', []);

            $emailLog = $this->emailService->sendCreditNote($creditNote, $customMessage, $uploadedFiles);

            if ($emailLog->getStatus() === 'sent') {
                $this->addFlash('success', sprintf('Avoir envoyé avec succès à %s', $client->getEmail()));
            } else {
                $this->addFlash('error', sprintf('Erreur lors de l\'envoi : %s', $emailLog->getErrorMessage()));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_credit_note_show', ['id' => $creditNote->getId()]);
    }
}
