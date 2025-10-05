<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/facture', name: 'admin_facture_')]
class FactureController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/facture/index.html.twig');
    }

    #[Route('/new', name: 'new')]
    public function new(): Response
    {
        return $this->render('admin/facture/new.html.twig');
    }
}
