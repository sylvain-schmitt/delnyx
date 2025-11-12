<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Form\QuoteType;
use App\Repository\QuoteRepository;
use App\Repository\ClientRepository;
use App\Repository\CompanySettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private EntityManagerInterface $entityManager
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

        // Récupérer les devis de la page courante
        $quotes = $qb->select('q')
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
        return $this->render('admin/quote/show.html.twig', [
            'quote' => $quote,
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

        return $this->render('admin/quote/form.html.twig', [
            'quote' => $quote,
            'form' => $form,
            'title' => $title,
            'companySettings' => $companySettings,
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

        return $this->render('admin/quote/form.html.twig', [
            'quote' => $quote,
            'form' => $form,
            'title' => $title,
            'companySettings' => $companySettings,
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Request $request, Quote $quote): Response
    {
        // Conformité légale française : on n'efface jamais un devis, on l'annule
        // Cela préserve la numérotation séquentielle et la traçabilité comptable

        // Vérifier si le devis peut être annulé
        // Les devis signés, acceptés, ou ayant généré une facture ne peuvent pas être annulés
        if ($quote->getStatut() === QuoteStatus::SIGNED || $quote->getStatut() === QuoteStatus::ACCEPTED) {
            $this->addFlash('error', 'Ce devis est ' . strtolower($quote->getStatut()->getLabel()) . ' et ne peut plus être annulé.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        // Vérifier si une facture a été générée depuis ce devis
        if ($quote->getInvoice()) {
            $this->addFlash('error', 'Ce devis a généré une facture et ne peut plus être annulé.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        // Vérifier si le devis est déjà annulé
        if ($quote->getStatut() === QuoteStatus::CANCELLED) {
            $this->addFlash('info', 'Ce devis est déjà annulé.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        if ($this->isCsrfTokenValid('cancel' . $quote->getId(), $request->request->get('_token'))) {
            // Annuler le devis au lieu de le supprimer (conformité légale)
            $quote->setStatut(QuoteStatus::CANCELLED);
            $quote->setDateModification(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', 'Devis annulé avec succès. Le numéro est conservé pour la traçabilité comptable.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_quote_index');
    }

    #[Route('/{id}/generate-invoice', name: 'generate_invoice', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function generateInvoice(Request $request, Quote $quote): Response
    {
        // Vérifier que le devis est signé
        if ($quote->getStatut() !== QuoteStatus::SIGNED) {
            $this->addFlash('error', 'Seuls les devis signés peuvent être convertis en facture.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        // Vérifier qu'une facture n'existe pas déjà
        if ($quote->getInvoice()) {
            $this->addFlash('error', 'Une facture existe déjà pour ce devis.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        if ($this->isCsrfTokenValid('generate_invoice' . $quote->getId(), $request->request->get('_token'))) {
            // TODO: Créer la facture depuis le devis
            // Cette fonctionnalité sera implémentée dans InvoiceController
            $this->addFlash('info', 'La génération de facture depuis un devis sera disponible prochainement.');
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
}
