<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Form\InvoiceType;
use App\Repository\InvoiceRepository;
use App\Repository\QuoteRepository;
use App\Repository\ClientRepository;
use App\Repository\CompanySettingsRepository;
use App\Repository\AmendmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/invoice', name: 'admin_invoice_')]
#[IsGranted('ROLE_USER')]
class InvoiceController extends AbstractController
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private QuoteRepository $quoteRepository,
        private ClientRepository $clientRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private AmendmentRepository $amendmentRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 15; // 15 factures par page
        $includeCancelled = $request->query->getBoolean('include_cancelled', false);

        // Construire la requête avec exclusion des factures annulées par défaut
        $qb = $this->invoiceRepository->createQueryBuilder('i');

        if (!$includeCancelled) {
            $qb->where('i.statut != :cancelled OR i.statut IS NULL')
                ->setParameter('cancelled', InvoiceStatus::CANCELLED->value);
        }

        // Récupérer le nombre total de factures (hors annulées si non demandé)
        $totalInvoices = (int) $qb->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Calculer le nombre total de pages
        $totalPages = (int) ceil($totalInvoices / $limit);

        // S'assurer que la page demandée existe
        $page = min($page, max(1, $totalPages));

        // Récupérer les factures de la page courante
        $invoices = $qb->select('i')
            ->orderBy('i.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/invoice/index.html.twig', [
            'invoices' => $invoices,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_invoices' => $totalInvoices,
            'include_cancelled' => $includeCancelled,
        ]);
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
            'adresseLivraison' => $client->getAdresseComplete(),
        ]);
    }

    /**
     * Endpoint API pour récupérer les informations d'un devis (pour pré-remplir la facture)
     */
    #[Route('/api/quote/{id}', name: 'api_quote_info', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getQuoteInfo(int $id): JsonResponse
    {
        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return new JsonResponse(['error' => 'Devis non trouvé'], 404);
        }

        // Vérifier que le devis appartient à la même entreprise
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }
        
        if ($companyId && $quote->getCompanyId() !== $companyId) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        // Vérifier que le devis est signé
        if ($quote->getStatut() !== \App\Entity\QuoteStatus::SIGNED) {
            return new JsonResponse(['error' => 'Seuls les devis signés peuvent être utilisés'], 400);
        }

        // Vérifier qu'une facture n'existe pas déjà
        if ($quote->getInvoice()) {
            return new JsonResponse(['error' => 'Une facture existe déjà pour ce devis'], 400);
        }

        // Préparer les lignes du devis
        $lines = [];
        foreach ($quote->getLines() as $quoteLine) {
            $lines[] = [
                'description' => $quoteLine->getDescription(),
                'quantity' => $quoteLine->getQuantity(),
                'unitPrice' => $quoteLine->getUnitPrice(),
                'totalHt' => $quoteLine->getTotalHt(),
                'tvaRate' => $quoteLine->getTvaRate(),
                'tariffId' => $quoteLine->getTariff()?->getId(),
            ];
        }

        return new JsonResponse([
            'id' => $quote->getId(),
            'numero' => $quote->getNumero(),
            'clientId' => $quote->getClient()?->getId(),
            'conditionsPaiement' => $quote->getConditionsPaiement(),
            'lines' => $lines,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Invoice $invoice): Response
    {
        // Récupérer les avenants du devis associé s'il existe
        $quoteAmendments = [];
        if ($invoice->getQuote()) {
            $quoteAmendments = $this->amendmentRepository->createQueryBuilder('a')
                ->where('a.quote = :quote')
                ->setParameter('quote', $invoice->getQuote())
                ->orderBy('a.dateCreation', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('admin/invoice/show.html.twig', [
            'invoice' => $invoice,
            'quoteAmendments' => $quoteAmendments,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $invoice = new Invoice();

        // Pré-remplir le company_id avec celui de l'utilisateur
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
            $invoice->setCompanyId($companyId);
        }

        // Pré-remplir la date d'échéance par défaut (30 jours)
        if (!$invoice->getDateEcheance()) {
            $dateEcheance = new \DateTime();
            $dateEcheance->modify('+30 days');
            $invoice->setDateEcheance($dateEcheance);
        }

        // Récupérer CompanySettings pour le formulaire
        $companySettings = null;
        if ($companyId) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($companyId);
        }

        // Pré-remplir depuis un devis si fourni en paramètre
        $quoteId = $request->query->get('quote_id');
        if ($quoteId) {
            $quote = $this->quoteRepository->find($quoteId);
            if ($quote && $quote->getStatut() === \App\Entity\QuoteStatus::SIGNED && !$quote->getInvoice()) {
                $invoice->setQuote($quote);
                $invoice->setClient($quote->getClient());
                
                // Pré-remplir la date d'échéance (30 jours par défaut)
                $dateEcheance = new \DateTime();
                $dateEcheance->modify('+30 days');
                $invoice->setDateEcheance($dateEcheance);
                
                // Copier les conditions de paiement
                if ($quote->getConditionsPaiement()) {
                    $invoice->setConditionsPaiement($quote->getConditionsPaiement());
                }
                
                // Copier les lignes du devis vers la facture
                foreach ($quote->getLines() as $quoteLine) {
                    $invoiceLine = new \App\Entity\InvoiceLine();
                    $invoiceLine->setDescription($quoteLine->getDescription());
                    $invoiceLine->setQuantity($quoteLine->getQuantity());
                    $invoiceLine->setUnitPrice($quoteLine->getUnitPrice());
                    $invoiceLine->setTotalHt($quoteLine->getTotalHt());
                    $invoiceLine->setTvaRate($quoteLine->getTvaRate());
                    $invoiceLine->setTariff($quoteLine->getTariff());
                    $invoice->addLine($invoiceLine);
                }
                
                // Recalculer les totaux
                $invoice->recalculateTotalsFromLines();
            }
        }

        $form = $this->createForm(InvoiceType::class, $invoice, [
            'company_settings' => $companySettings,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérifier qu'au moins une ligne est présente
                if ($invoice->getLines()->isEmpty()) {
                    $this->addFlash('error', 'Au moins une ligne de facture est requise.');
                } else {
                    // Pré-remplir le client depuis le devis si présent
                    if ($invoice->getQuote() && !$invoice->getClient()) {
                        $invoice->setClient($invoice->getQuote()->getClient());
                    }

                    // Associer les lignes à la facture et calculer les totaux
                    foreach ($invoice->getLines() as $line) {
                        $line->setInvoice($invoice);

                        // Définir automatiquement isCustom : true si aucun tarif, false si tarif sélectionné
                        if ($line->getTariff()) {
                            // Pas de isCustom pour InvoiceLine, mais on peut le gérer via tariff null
                        } else {
                            // Ligne personnalisée
                        }

                        // Gestion TVA: récupérer depuis le Quote associé ou CompanySettings
                        $quote = $invoice->getQuote();
                        $tvaEnabled = true;
                        if ($companyId) {
                            $companySettings = $companySettings ?? $this->companySettingsRepository->findByCompanyId($companyId);
                            $tvaEnabled = $companySettings ? (method_exists($companySettings, 'isTvaEnabled') ? $companySettings->isTvaEnabled() : true) : true;
                        }

                        if (!$tvaEnabled) {
                            $line->setTvaRate('0');
                        } else {
                            // TVA activée : si usePerLineTva = false et aucune TVA définie en ligne, appliquer le taux du quote
                            if ($quote && !$quote->isUsePerLineTva() && !$line->getTvaRate()) {
                                $line->setTvaRate($quote->getTauxTVA());
                            }
                        }
                        // Recalculer le total HT de la ligne
                        $line->recalculateTotalHt();
                    }

                    // Recalculer les totaux de la facture depuis les lignes
                    $invoice->recalculateTotalsFromLines();

                    // Générer le numéro si ce n'est pas déjà fait (fallback si l'EventSubscriber ne fonctionne pas)
                    if (!$invoice->getNumero()) {
                        $year = (int) date('Y');

                        // Trouver le dernier numéro pour cette année
                        $lastInvoice = $this->invoiceRepository->createQueryBuilder('i')
                            ->where('i.numero LIKE :pattern')
                            ->setParameter('pattern', sprintf('FACT-%d-%%', $year))
                            ->orderBy('i.numero', 'DESC')
                            ->setMaxResults(1)
                            ->getQuery()
                            ->getOneOrNullResult();

                        $sequence = 1;
                        if ($lastInvoice && $lastInvoice->getNumero()) {
                            // Extraire le numéro de séquence du dernier facture
                            $parts = explode('-', $lastInvoice->getNumero());
                            if (count($parts) === 3) {
                                $sequence = (int) $parts[2] + 1;
                            }
                        }

                        $invoice->setNumero(sprintf('FACT-%d-%03d', $year, $sequence));
                    }

                    $this->entityManager->persist($invoice);
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Facture créée avec succès');
                    return $this->redirectToRoute('admin_invoice_index');
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

        $title = 'Nouvelle Facture';
        if ($invoice->getId() && $invoice->getNumero()) {
            $title = 'Modifier la facture ' . $invoice->getNumero();
        }

        return $this->render('admin/invoice/form.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'title' => $title,
            'companySettings' => $companySettings,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Invoice $invoice): Response
    {
        // Vérifier si la facture peut être modifiée
        if (!$invoice->canBeModified()) {
            $this->addFlash('error', 'Cette facture ne peut plus être modifiée.');
            return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
        }

        // Vérifier si la facture est annulée (ne peut pas être modifiée)
        if ($invoice->getStatut() === InvoiceStatus::CANCELLED->value) {
            $this->addFlash('error', 'Cette facture est annulée et ne peut plus être modifiée.');
            return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
        }

        // Récupérer CompanySettings pour le formulaire
        $companySettings = null;
        if ($invoice->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($invoice->getCompanyId());
        }

        $form = $this->createForm(InvoiceType::class, $invoice, [
            'company_settings' => $companySettings,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Vérifier qu'au moins une ligne est présente
                if ($invoice->getLines()->isEmpty()) {
                    $this->addFlash('error', 'Au moins une ligne de facture est requise.');
                } else {
                    // Pré-remplir le client depuis le devis si présent
                    if ($invoice->getQuote() && !$invoice->getClient()) {
                        $invoice->setClient($invoice->getQuote()->getClient());
                    }

                    // Associer les lignes à la facture et calculer les totaux
                    foreach ($invoice->getLines() as $line) {
                        $line->setInvoice($invoice);

                        // Gestion TVA: récup config
                        $companySettings = null;
                        $tvaEnabled = true;
                        if ($invoice->getCompanyId()) {
                            $companySettings = $this->companySettingsRepository->findByCompanyId($invoice->getCompanyId());
                            $tvaEnabled = $companySettings ? (method_exists($companySettings, 'isTvaEnabled') ? $companySettings->isTvaEnabled() : true) : true;
                        }

                        // Ne pas réinitialiser les taux de TVA des lignes existantes lors de l'édition
                        // Seulement appliquer un taux par défaut si la ligne n'en a pas encore
                        $quote = $invoice->getQuote();
                        if (!$tvaEnabled) {
                            // Si TVA désactivée et ligne sans taux : forcer à 0
                            // Sinon, préserver le taux existant pour l'historique
                            if (!$line->getTvaRate()) {
                                $line->setTvaRate('0');
                            }
                        } else {
                            // Si TVA activée : appliquer le taux du quote seulement si la ligne n'a pas de taux
                            // et que usePerLineTva est false
                            if ($quote && !$quote->isUsePerLineTva() && !$line->getTvaRate()) {
                                $line->setTvaRate($quote->getTauxTVA());
                            }
                        }
                        // Recalculer le total HT de la ligne
                        $line->recalculateTotalHt();
                    }

                    // Recalculer les totaux de la facture depuis les lignes
                    $invoice->recalculateTotalsFromLines();

                    $invoice->setDateModification(new \DateTime());
                    $this->entityManager->flush();

                    $this->addFlash('success', 'Facture modifiée avec succès');
                    return $this->redirectToRoute('admin_invoice_index');
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
        if ($invoice->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($invoice->getCompanyId());
        }

        $title = 'Modifier la Facture';
        if ($invoice->getNumero()) {
            $title = 'Modifier la facture ' . $invoice->getNumero();
        }

        return $this->render('admin/invoice/form.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'title' => $title,
            'companySettings' => $companySettings,
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Request $request, Invoice $invoice): Response
    {
        // Conformité légale française : on n'efface jamais une facture, on l'annule
        // Cela préserve la numérotation séquentielle et la traçabilité comptable

        // Vérifier si la facture peut être annulée
        if (!$invoice->canBeCancelled()) {
            $this->addFlash('error', 'Cette facture ne peut plus être annulée.');
            return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
        }

        // Vérifier explicitement que la facture n'est pas payée
        if ($invoice->getStatut() === InvoiceStatus::PAID->value) {
            $this->addFlash('error', 'Une facture payée ne peut pas être annulée. Créez un avoir pour rembourser.');
            return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
        }

        // Vérifier si la facture est déjà annulée
        if ($invoice->getStatut() === InvoiceStatus::CANCELLED->value) {
            $this->addFlash('info', 'Cette facture est déjà annulée.');
            return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
        }

        if ($this->isCsrfTokenValid('cancel' . $invoice->getId(), $request->request->get('_token'))) {
            // Annuler la facture au lieu de la supprimer (conformité légale)
            $invoice->setStatutEnum(InvoiceStatus::CANCELLED);
            $invoice->setDateModification(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', 'Facture annulée avec succès. Le numéro est conservé pour la traçabilité comptable.');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('admin_invoice_index');
    }

    #[Route('/generate-from-quote/{id}', name: 'generate_from_quote', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function generateFromQuote(Request $request, Quote $quote): Response
    {
        // Vérifier que le devis appartient à la même entreprise
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }
        
        if ($companyId && $quote->getCompanyId() !== $companyId) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce devis.');
            return $this->redirectToRoute('admin_quote_index');
        }

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
            // Créer la facture depuis le devis
            $invoice = new Invoice();
            $invoice->setQuote($quote);
            $invoice->setClient($quote->getClient());
            $invoice->setCompanyId($quote->getCompanyId());

            // Pré-remplir la date d'échéance (30 jours par défaut)
            $dateEcheance = new \DateTime();
            $dateEcheance->modify('+30 days');
            $invoice->setDateEcheance($dateEcheance);

            // Copier les conditions de paiement
            if ($quote->getConditionsPaiement()) {
                $invoice->setConditionsPaiement($quote->getConditionsPaiement());
            }

            // Copier les lignes du devis vers la facture
            foreach ($quote->getLines() as $quoteLine) {
                $invoiceLine = new \App\Entity\InvoiceLine();
                $invoiceLine->setDescription($quoteLine->getDescription());
                $invoiceLine->setQuantity($quoteLine->getQuantity());
                $invoiceLine->setUnitPrice($quoteLine->getUnitPrice());
                $invoiceLine->setTotalHt($quoteLine->getTotalHt());
                $invoiceLine->setTvaRate($quoteLine->getTvaRate());
                $invoiceLine->setTariff($quoteLine->getTariff());
                $invoiceLine->setInvoice($invoice);
                $invoice->addLine($invoiceLine);
            }

            // Recalculer les totaux
            $invoice->recalculateTotalsFromLines();

            // Le numéro sera généré automatiquement par l'EventSubscriber
            $this->entityManager->persist($invoice);
            $this->entityManager->flush();

            $this->addFlash('success', 'Facture créée depuis le devis avec succès');
            return $this->redirectToRoute('admin_invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
    }
}

