<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/devis', name: 'admin_devis_')]
class DevisController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/devis/index.html.twig');
    }

    #[Route('/new', name: 'new')]
    public function new(): Response
    {
        return $this->render('admin/devis/new.html.twig');
    }
}
