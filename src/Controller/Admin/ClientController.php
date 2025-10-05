<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/client', name: 'admin_client_')]
class ClientController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $clients = $this->clientRepository->findAll();

        return $this->render('admin/client/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(): Response
    {
        return $this->render('admin/client/new.html.twig');
    }
}
