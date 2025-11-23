<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/client', name: 'admin_client_')]
class ClientController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12; // 12 clients par page (3x4 grid)

        // Récupérer le nombre total de clients
        $totalClients = $this->clientRepository->count([]);
        
        // Calculer le nombre total de pages
        $totalPages = (int) ceil($totalClients / $limit);
        
        // S'assurer que la page demandée existe
        $page = min($page, max(1, $totalPages));
        
        // Récupérer les clients de la page courante
        $clients = $this->clientRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        return $this->render('admin/client/index.html.twig', [
            'clients' => $clients,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_clients' => $totalClients,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($client);
            $this->entityManager->flush();

            $this->addFlash('success', 'Client créé avec succès');
            return $this->redirectToRoute('admin_client_index');
        }

        return $this->render('admin/client/form.html.twig', [
            'client' => $client,
            'form' => $form,
            'title' => 'Nouveau Client',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Client $client): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Client modifié avec succès');
            return $this->redirectToRoute('admin_client_index');
        }

        return $this->render('admin/client/form.html.twig', [
            'client' => $client,
            'form' => $form,
            'title' => 'Modifier le Client',
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Client $client): Response
    {
        if ($this->isCsrfTokenValid('delete' . $client->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($client);
            $this->entityManager->flush();

            $this->addFlash('success', 'Client supprimé avec succès');
        }

        return $this->redirectToRoute('admin_client_index');
    }
}
