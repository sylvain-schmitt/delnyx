<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Form\QuoteType;
use App\Repository\QuoteRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 15; // 15 devis par page

        // Récupérer le nombre total de devis
        $totalQuotes = $this->quoteRepository->count([]);

        // Calculer le nombre total de pages
        $totalPages = (int) ceil($totalQuotes / $limit);

        // S'assurer que la page demandée existe
        $page = min($page, max(1, $totalPages));

        // Récupérer les devis de la page courante
        $quotes = $this->quoteRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        return $this->render('admin/quote/index.html.twig', [
            'quotes' => $quotes,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_quotes' => $totalQuotes,
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
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
            $quote->setCompanyId($companyId);
        }

        $form = $this->createForm(QuoteType::class, $quote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($quote);
            $this->entityManager->flush();

            $this->addFlash('success', 'Devis créé avec succès');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        return $this->render('admin/quote/form.html.twig', [
            'quote' => $quote,
            'form' => $form,
            'title' => 'Nouveau Devis',
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

        $form = $this->createForm(QuoteType::class, $quote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quote->setDateModification(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', 'Devis modifié avec succès');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        return $this->render('admin/quote/form.html.twig', [
            'quote' => $quote,
            'form' => $form,
            'title' => 'Modifier le Devis',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Quote $quote): Response
    {
        // Vérifier si le devis est signé (ne peut pas être supprimé)
        if ($quote->getStatut() === QuoteStatus::SIGNED) {
            $this->addFlash('error', 'Ce devis est signé et ne peut plus être supprimé.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $quote->getId()]);
        }

        if ($this->isCsrfTokenValid('delete' . $quote->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($quote);
            $this->entityManager->flush();

            $this->addFlash('success', 'Devis supprimé avec succès');
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
}
