<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Quote;
use App\Entity\Deposit;
use App\Entity\QuoteStatus;
use App\Form\QuoteType;
use App\Repository\QuoteRepository;
use App\Repository\ClientRepository;
use App\Repository\CompanySettingsRepository;
use App\Repository\AmendmentRepository;
use App\Repository\TariffRepository;
use App\Service\QuoteService;
use App\Service\InvoiceService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/quote', name: 'admin_quote_')]
#[IsGranted('ROLE_USER')]
class QuoteController extends AbstractController
{
    public function __construct(
        private QuoteRepository $quoteRepository,
        private ClientRepository $clientRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private AmendmentRepository $amendmentRepository,
        private TariffRepository $tariffRepository,
        private EntityManagerInterface $entityManager,
        private QuoteService $quoteService,
        private InvoiceService $invoiceService,
        private \App\Service\PdfGeneratorService $pdfGeneratorService,
        private EmailService $emailService
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 15; // 15 devis par page
        $includeCancelled = $request->query->getBoolean('include_cancelled', false);

        // Construire la requête avec exclusion des devis annulés par défaut
        $qb = $this->quoteRepository->createQueryBuilder('q');

        if (!$includeCancelled) {
            $qb->where('q.statut != :cancelled')
                ->setParameter('cancelled', QuoteStatus::CANCELLED);
        }

        // Récupérer le nombre total de devis (hors annulés si non demandé)
        $totalQuotes = (int) $qb->select('COUNT(q.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Calculer le nombre total de pages
        $totalPages = (int) ceil($totalQuotes / $limit);

        // S'assurer que la page demandée existe
        $page = min($page, max(1, $totalPages));

        // Récupérer les devis de la page courante avec eager loading des avenants et clients
        $quotes = $qb->select('q')
            ->leftJoin('q.client', 'c')
            ->addSelect('c')
            ->leftJoin('q.amendments', 'a')
            ->addSelect('a')
            ->orderBy('q.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/quote/index.html.twig', [
            'quotes' => $quotes,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_quotes' => $totalQuotes,
            'include_cancelled' => $includeCancelled,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Quote $quote): Response
    {
        // Charger explicitement la relation invoice pour éviter les requêtes N+1
        // Recharger le quote avec ses relations
        $quote = $this->quoteRepository->createQueryBuilder('q')
            ->leftJoin('q.invoice', 'i')
            ->addSelect('i')
            ->where('q.id = :id')
            ->setParameter('id', $quote->getId())
            ->getQuery()
            ->getOneOrNullResult() ?? $quote;

        // Récupérer les avenants liés à ce devis
        $amendments = $this->amendmentRepository->createQueryBuilder('a')
            ->where('a.quote = :quote')
            ->setParameter('quote', $quote)
            ->orderBy('a.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();

        // Récupérer CompanySettings pour l'affichage
        $companySettings = null;
        if ($quote->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($quote->getCompanyId());
        }

        return $this->render('admin/quote/show.html.twig', [
            'quote' => $quote,
            'amendments' => $amendments,
            'companySettings' => $companySettings,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $quote = new Quote();

        // Pré-remplir le company_id avec celui de l'utilisateur
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
            $quote->setCompanyId($companyId);
        }

        // Pré-remplir le taux de TVA depuis CompanySettings (même si 0% pour micro-entrepreneur)
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
            if ($companySettings) {
                // Si TVA désactivée, forcer 0% et ignorer la TVA par ligne
                if (method_exists($companySettings, 'isTvaEnabled') && !$companySettings->isTvaEnabled()) {
                    $quote->setTauxTVA('0.00');
                    $quote->setUsePerLineTva(false);
                } else {
                    $quote->setTauxTVA($companySettings->getTauxTVADefaut());
                }
            }
        }

        // Pré-remplir la date de validité par défaut (30 jours = 1 mois, durée légale pour un devis)
        if (!$quote->getDateValidite()) {
            $dateValidite = new \DateTime();
            $dateValidite->modify('+30 days');
            $quote->setDateValidite($dateValidite);
        }

        // Récupérer CompanySettings pour le formulaire
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }

        $form = $this->createForm(QuoteType::class, $quote, [
            'company_settings' => $companySettings,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérifier qu'au moins une ligne est présente
                if ($quote->getLines()->isEmpty()) {
                    $this->addFlash('error', 'Au moins une ligne de devis est requise.');
                } else {
                    // Pré-remplir sirenClient et adresseLivraison depuis le client
                    if ($quote->getClient()) {
                        $client = $quote->getClient();
                        // SIREN extrait du SIRET (9 premiers chiffres)
                        if ($client->getSiret() && !$quote->getSirenClient()) {
                            $quote->setSirenClient($client->getSiren());
                        }
                        // Adresse de livraison depuis l'adresse complète du client
                        if (!$quote->getAdresseLivraison()) {
                            $quote->setAdresseLivraison($client->getAdresseComplete());
                        }
                    }

                    // Associer les lignes au devis et calculer les totaux
                    foreach ($quote->getLines() as $line) {
                        $line->setQuote($quote);

                        // Définir automatiquement isCustom : true si aucun tarif, false si tarif sélectionné
                        if ($line->getTariff()) {
                            $line->setIsCustom(false);
                        } else {
                            $line->setIsCustom(true);
                        }

                        // Gestion TVA: si désactivée globalement -> forcer 0 par ligne
                        if ($companyId) {
                            $companySettings = $companySettings ?? $this->companySettingsRepository->findByCompanyId($companyId);
                        }
                        $tvaEnabled = $companySettings ? (method_exists($companySettings, 'isTvaEnabled') ? $companySettings->isTvaEnabled() : true) : true;

                        if (!$tvaEnabled) {
                            $line->setTvaRate('0');
                        } else {
                            // TVA activée : si usePerLineTva = false et aucune TVA définie en ligne, appliquer le taux global
                            if (!$quote->isUsePerLineTva() && !$line->getTvaRate()) {
                                $line->setTvaRate($quote->getTauxTVA());
                            }
                        }
                        // Recalculer le total HT de la ligne
                        $line->recalculateTotalHt();
                    }

                    // Recalculer les totaux du devis depuis les lignes en respectant usePerLineTva / TVA globale
                    $quote->recalculateTotalsFromLines();

                    // Si le statut est SIGNED, valider maintenant que les lignes sont bien associées
                    if ($quote->getStatut() === QuoteStatus::SIGNED) {
                        try {
                            $quote->validateCanBeSigned();
                        } catch (\RuntimeException $e) {
                            $this->addFlash('error', $e->getMessage());
                            // Ne pas persister si la validation échoue
                            return $this->render('admin/quote/form.html.twig', [
                                'quote' => $quote,
                                'form' => $form,
                                'title' => 'Nouveau Devis',
                                'companySettings' => $companySettings,
                            ]);
                        }
                    }

                    // Générer le numéro si ce n'est pas déjà fait (fallback si l'EventSubscriber ne fonctionne pas)
                    if (!$quote->getNumero()) {
                        $year = (int) date('Y');

                        // Trouver le dernier numéro pour cette année
                        // Support des deux formats : ancien DEV-YYYY-MM-XXX et nouveau DEV-YYYY-XXX
                        $lastQuote = $this->quoteRepository->createQueryBuilder('q')
                            ->where('q.numero LIKE :pattern')
                            ->setParameter('pattern', sprintf('DEV-%d-%%', $year))
                            ->orderBy('q.numero', 'DESC')
                            ->setMaxResults(1)
                            ->getQuery()
                            ->getOneOrNullResult();

                        $sequence = 1;
                        if ($lastQuote && $lastQuote->getNumero()) {
                            // Extraire le numéro de séquence du dernier devis
                            // Support des deux formats :
                            // - Ancien : DEV-YYYY-MM-XXX (4 parties)
                            // - Nouveau : DEV-YYYY-XXX (3 parties)
                            $parts = explode('-', $lastQuote->getNumero());
                            if (count($parts) === 4) {
                                // Ancien format : DEV-YYYY-MM-XXX
                                $sequence = (int) $parts[3] + 1;
                            } elseif (count($parts) === 3 && is_numeric($parts[2])) {
                                // Nouveau format : DEV-YYYY-XXX
                                $sequence = (int) $parts[2] + 1;
                            }
                        }

                        // Générer le numéro au format DEV-YYYY-XXX (ex: DEV-2025-001)
                        $quote->setNumero(sprintf('DEV-%d-%03d', $year, $sequence));
                    }

                    $this->entityManager->persist($quote);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Devis créé avec succès');
                    return $this->redirectToRoute('admin_quote_index');
                }
            } else {
                // Afficher les erreurs de validation
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('error', 'Erreurs de validation : ' . implode(', ', array_slice($errors, 0, 5)));
                }
            }
        }

        // Récupérer CompanySettings pour l'affichage conditionnel dans le template
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }

        $title = 'Nouveau Devis';
        if ($quote->getId() && $quote->getNumero()) {
            $title = 'Modifier le devis ' . $quote->getNumero();
        }

        // Récupérer les tarifs actifs pour le template des nouvelles lignes
        $tariffs = $this->tariffRepository->findBy(['actif' => true], ['ordre' => 'ASC', 'nom' => 'ASC']);

        return $this->render('admin/quote/form.html.twig', [
            'quote' => $quote,
            'form' => $form,
            'title' => $title,
            'companySettings' => $companySettings,
            'tariffs' => $tariffs,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Quote $quote): Response
    {
        // Vérifier si le devis est signé (ne peut pas être modifié)
        if ($quote->getStatut() === QuoteStatus::SIGNED) {
            $this->addFlash('error', 'Ce devis est signé et ne peut plus être modifié. Créez un avenant pour le modifier.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        // Vérifier si le devis est annulé (ne peut pas être modifié)
        if ($quote->getStatut() === QuoteStatus::CANCELLED) {
            $this->addFlash('error', 'Ce devis est annulé et ne peut plus être modifié.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        // Récupérer CompanySettings pour le formulaire
        $companySettings = null;
        if ($quote->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($quote->getCompanyId());
        }

        $form = $this->createForm(QuoteType::class, $quote, [
            'company_settings' => $companySettings,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérifier qu'au moins une ligne est présente
                if ($quote->getLines()->isEmpty()) {
                    $this->addFlash('error', 'Au moins une ligne de devis est requise.');
                } else {
                    // Pré-remplir sirenClient et adresseLivraison depuis le client
                    if ($quote->getClient()) {
                        $client = $quote->getClient();
                        // SIREN extrait du SIRET (9 premiers chiffres)
                        if ($client->getSiret() && !$quote->getSirenClient()) {
                            $quote->setSirenClient($client->getSiren());
                        }
                        // Adresse de livraison depuis l'adresse complète du client
                        if (!$quote->getAdresseLivraison()) {
                            $quote->setAdresseLivraison($client->getAdresseComplete());
                        }
                    }

                    // Associer les lignes au devis et calculer les totaux
                    foreach ($quote->getLines() as $line) {
                        $line->setQuote($quote);

                        // Définir automatiquement isCustom : true si aucun tarif, false si tarif sélectionné
                        if ($line->getTariff()) {
                            $line->setIsCustom(false);
                        } else {
                            $line->setIsCustom(true);
                        }

                        // Gestion TVA: récup config
                        $companySettings = null;
                        $tvaEnabled = true;
                        if ($quote->getCompanyId()) {
                            $companySettings = $this->companySettingsRepository->findByCompanyId($quote->getCompanyId());
                            $tvaEnabled = $companySettings ? (method_exists($companySettings, 'isTvaEnabled') ? $companySettings->isTvaEnabled() : true) : true;
                        }

                        // Ne pas réinitialiser les taux de TVA des lignes existantes lors de l'édition
                        // Seulement appliquer un taux par défaut si la ligne n'en a pas encore
                        if (!$tvaEnabled) {
                            // Si TVA désactivée et ligne sans taux : forcer à 0
                            // Sinon, préserver le taux existant pour l'historique
                            if (!$line->getTvaRate()) {
                                $line->setTvaRate('0');
                            }
                        } else {
                            // Si TVA activée : appliquer le taux global seulement si la ligne n'a pas de taux
                            // et que usePerLineTva est false
                            if (!$quote->isUsePerLineTva() && !$line->getTvaRate()) {
                                $line->setTvaRate($quote->getTauxTVA());
                            }
                        }
                        // Recalculer le total HT de la ligne
                        $line->recalculateTotalHt();
                    }

                    // Recalculer les totaux du devis depuis les lignes en respectant usePerLineTva / TVA globale
                    $quote->recalculateTotalsFromLines();

                    // Si le statut est SIGNED, valider maintenant que les lignes sont bien associées
                    if ($quote->getStatut() === QuoteStatus::SIGNED) {
                        try {
                            $quote->validateCanBeSigned();
                        } catch (\RuntimeException $e) {
                            $this->addFlash('error', $e->getMessage());
                            // Ne pas persister si la validation échoue
                            return $this->render('admin/quote/form.html.twig', [
                                'quote' => $quote,
                                'form' => $form,
                                'title' => 'Modifier le devis ' . $quote->getNumero(),
                                'companySettings' => $companySettings,
                            ]);
                        }
                    }

                    $quote->setDateModification(new \DateTime());
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Devis modifié avec succès');
                    return $this->redirectToRoute('admin_quote_index');
                }
            } else {
                // Afficher les erreurs de validation
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                if (!empty($errors)) {
                    $this->addFlash('error', 'Erreurs de validation : ' . implode(', ', array_slice($errors, 0, 5)));
                }
            }
        }

        // Récupérer CompanySettings pour l'affichage conditionnel dans le template
        $companySettings = null;
        if ($quote->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($quote->getCompanyId());
        }

        $title = 'Modifier le Devis';
        if ($quote->getNumero()) {
            $title = 'Modifier le devis ' . $quote->getNumero();
        }

        // Récupérer les tarifs actifs pour le template des nouvelles lignes
        $tariffs = $this->tariffRepository->findBy(['actif' => true], ['ordre' => 'ASC', 'nom' => 'ASC']);

        return $this->render('admin/quote/form.html.twig', [
            'quote' => $quote,
            'form' => $form,
            'title' => $title,
            'companySettings' => $companySettings,
            'tariffs' => $tariffs,
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_CANCEL', subject: 'quote')]
    public function cancel(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_cancel_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            $reason = $request->request->get('reason');
            $otherReason = $request->request->get('other_reason');

            // Si "Autre" est sélectionné, utiliser la raison personnalisée
            $finalReason = ($reason === 'Autre' && $otherReason) ? $otherReason : $reason;

            // Vérifier qu'une raison a été fournie
            if (empty($finalReason)) {
                $this->addFlash('error', 'Veuillez sélectionner une raison d\'annulation.');
                return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
            }

            $this->quoteService->cancel($quote, $finalReason);
            $this->addFlash('success', 'Devis annulé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    /**
     * Repasse un devis SENT en DRAFT pour modification
     */
    #[Route('/{id}/back-to-draft', name: 'back_to_draft', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_EDIT', subject: 'quote')]
    public function backToDraft(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_back_to_draft_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            $this->quoteService->backToDraft($quote);
            $this->addFlash('success', 'Le devis est repassé en brouillon. Vous pouvez maintenant le modifier.');
            return $this->redirectToRoute('admin_quote_edit', ['id' => $quote->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }
    }

    /**
     * Envoie un email de relance au client
     */
    #[Route('/{id}/remind', name: 'remind', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_SEND', subject: 'quote')]
    public function remind(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_remind_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            // Enregistrer la relance
            $this->quoteService->remind($quote);

            // Envoyer l'email de relance (avec template spécifique)
            $customMessage = $request->request->get('custom_message', 'Nous vous rappelons que ce devis est en attente de votre retour.');
            $uploadedFiles = $request->files->get('attachments', []);

            $emailLog = $this->emailService->sendQuote($quote, $customMessage, $uploadedFiles);

            if ($emailLog->getStatus() === 'sent') {
                $this->addFlash('success', sprintf('Relance envoyée avec succès à %s', $quote->getClient()->getEmail()));
            } else {
                $this->addFlash('error', sprintf('Erreur lors de l\'envoi de la relance : %s', $emailLog->getErrorMessage()));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de la relance : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }


    /**
     * Endpoint API pour récupérer les informations d'un client (SIREN, etc.)
     */
    #[Route('/api/client/{id}', name: 'api_client_info', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getClientInfo(int $id): JsonResponse
    {
        $client = $this->clientRepository->find($id);

        if (!$client) {
            return new JsonResponse(['error' => 'Client non trouvé'], 404);
        }

        return new JsonResponse([
            'id' => $client->getId(),
            'siren' => $client->getSiren(),
            'siret' => $client->getSiret(),
            'adresse' => $client->getAdresseComplete(),
            'adresseLivraison' => $client->getAdresseComplete(), // Pour pré-remplir l'adresse de livraison
        ]);
    }

    /**
     * Envoie un devis (DRAFT → SENT)
     */
    #[Route('/{id}/issue', name: 'issue', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_ISSUE', subject: 'quote')]
    public function issue(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_issue_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            $this->quoteService->issue($quote);
            $this->addFlash('success', 'Devis émis avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    #[Route('/{id}/send', name: 'send', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_SEND', subject: 'quote')]
    public function send(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_send_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            $this->quoteService->send($quote);
            $this->addFlash('success', 'Devis envoyé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    /**
     * Accepte un devis (SENT → ACCEPTED)
     */
    #[Route('/{id}/accept', name: 'accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_ACCEPT', subject: 'quote')]
    public function accept(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_accept_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            $this->quoteService->accept($quote);
            $this->addFlash('success', 'Devis accepté avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    /**
     * Signe un devis (SENT/ACCEPTED → SIGNED)
     */
    #[Route('/{id}/sign', name: 'sign', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_SIGN', subject: 'quote')]
    public function sign(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_sign_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            $signatureClient = $request->request->get('signatureClient');
            $this->quoteService->sign($quote, $signatureClient);
            $this->addFlash('success', 'Devis signé avec succès - CONTRAT créé.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    /**
     * Refuse un devis (SENT/ACCEPTED → REFUSED)
     */
    #[Route('/{id}/refuse', name: 'refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_REFUSE', subject: 'quote')]
    public function refuse(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_refuse_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            $reason = $request->request->get('reason');
            $this->quoteService->refuse($quote, $reason);
            $this->addFlash('success', 'Devis refusé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    /**
     * Génère et affiche le PDF du devis dans le navigateur
     */
    #[Route('/{id}/pdf', name: 'pdf', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function pdf(Quote $quote): Response
    {
        try {
            return $this->pdfGeneratorService->generateDevisPdf($quote, false);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF : ' . $e->getMessage());
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }
    }

    /**
     * Télécharge le PDF du devis (génère et sauvegarde si nécessaire)
     */
    #[Route('/{id}/download-pdf', name: 'download_pdf', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function downloadPdf(Quote $quote): Response
    {
        try {
            // Si le PDF n'a pas encore été généré, le générer et sauvegarder
            if (!$quote->getPdfFilename()) {
                $result = $this->pdfGeneratorService->generateDevisPdf($quote, true);

                // Sauvegarder le nom de fichier et le hash dans l'entité
                $quote->setPdfFilename($result['filename']);
                $quote->setPdfHash($result['hash']);
                $this->entityManager->flush();

                // Retourner la réponse PDF
                return $result['response'];
            }

            // Si le PDF existe déjà, le retourner depuis le fichier sauvegardé
            $filePath = $this->getParameter('kernel.project_dir') . '/var/generated_pdfs/' . $quote->getPdfFilename();

            if (!file_exists($filePath)) {
                // Le fichier n'existe plus, régénérer
                $result = $this->pdfGeneratorService->generateDevisPdf($quote, true);
                $quote->setPdfFilename($result['filename']);
                $quote->setPdfHash($result['hash']);
                $this->entityManager->flush();

                return $result['response'];
            }

            // Retourner le fichier existant
            return $this->file($filePath, 'devis-' . $quote->getNumero() . '.pdf', ResponseHeaderBag::DISPOSITION_INLINE);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du téléchargement du PDF : ' . $e->getMessage());
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }
    }

    /**
     * Génère une facture depuis un devis signé
     */
    #[Route('/{id}/generate-invoice', name: 'generate_invoice', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('QUOTE_GENERATE_INVOICE', subject: 'quote')]
    public function generateInvoice(Quote $quote, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quote_generate_invoice_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            // Créer la facture depuis le devis en statut brouillon (DRAFT)
            // L'utilisateur pourra ensuite l'émettre manuellement quand il le souhaite
            $invoice = $this->invoiceService->createFromQuote($quote, false);

            $this->addFlash('success', sprintf('Facture créée avec succès : %s', $invoice->getNumero() ?? 'N/A'));
            return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }
    }

    /**
     * Envoie le devis par email
     * Change le statut DRAFT → SENT puis envoie l'email
     */
    #[Route('/{id}/send-email', name: 'send_email', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendEmail(Request $request, Quote $quote): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('quote_send_email_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        // Vérifier que le devis a un client avec un email
        $client = $quote->getClient();

        if (!$client || !$client->getEmail()) {
            $this->addFlash('error', 'Impossible d\'envoyer le devis : aucun email client configuré.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            // 1. Changer le statut (DRAFT/ISSUED → SENT)
            // Ceci gère aussi la génération du PDF et la validation
            try {
                $this->quoteService->send($quote);
            } catch (\RuntimeException $e) {
                // Si la transition échoue, on continue quand même pour permettre le renvoi
                // (cas où le devis est déjà SENT)
                // (cas où le devis est déjà SENT)
            }

            // 2. Envoyer l'email
            $customMessage = $request->request->get('custom_message');
            $uploadedFiles = $request->files->get('attachments', []);

            $emailLog = $this->emailService->sendQuote($quote, $customMessage, $uploadedFiles);

            if ($emailLog->getStatus() === 'sent') {
                $this->addFlash('success', sprintf('Devis envoyé avec succès à %s', $client->getEmail()));
            } else {
                $this->addFlash('error', sprintf('Erreur lors de l\'envoi : %s', $emailLog->getErrorMessage()));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }

    /**
     * Demande un accompte pour un devis signé
     */
    #[Route('/{id}/request-deposit', name: 'request_deposit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function requestDeposit(
        Quote $quote,
        Request $request,
        \App\Service\DepositService $depositService,
        \App\Service\EmailService $emailService
    ): Response {
        if (!$this->isCsrfTokenValid('request_deposit_' . $quote->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        try {
            // Vérifier que le devis peut recevoir un accompte
            if (!$quote->canRequestDeposit()) {
                throw new \RuntimeException('Ce devis ne peut pas recevoir d\'accompte (doit être signé et non facturé).');
            }

            // Récupérer les données du formulaire
            $percentage = $request->request->get('percentage');
            $amount = $request->request->get('amount');

            // Si le pourcentage est vide mais qu'on a un montant, on passe null pour le pourcentage par défaut
            // Le service recalculera le pourcentage basé sur le montant
            $percentageFloat = $percentage !== null && $percentage !== '' ? (float) $percentage : Deposit::DEFAULT_PERCENTAGE;

            // Convertir montant en centimes si présent
            $amountInCents = $amount !== null && $amount !== '' ? (int) round((float) $amount * 100) : null;

            $deposit = $depositService->createDeposit($quote, $percentageFloat, $amountInCents);

            // 1. Créer la facture d'acompte (obligation légale pour acompte demandé)
            $depositService->getOrCreateDepositInvoice($deposit, \App\Entity\InvoiceStatus::ISSUED);

            // Envoyer automatiquement l'email de demande d'acompte
            $client = $quote->getClient();
            if ($client && $client->getEmail()) {
                try {
                    $emailService->sendDepositRequest($deposit);
                    $this->addFlash('success', sprintf(
                        'Accompte de %s créé et email envoyé au client.',
                        $deposit->getFormattedAmount()
                    ));
                } catch (\Exception $emailException) {
                    $this->addFlash('warning', sprintf(
                        'Accompte de %s créé mais l\'email n\'a pas pu être envoyé : %s',
                        $deposit->getFormattedAmount(),
                        $emailException->getMessage()
                    ));
                }
            } else {
                $this->addFlash('success', sprintf(
                    'Accompte de %s créé avec succès (pas d\'email client).',
                    $deposit->getFormattedAmount()
                ));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }
}
