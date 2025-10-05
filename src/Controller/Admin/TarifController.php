<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/tarif', name: 'admin_tarif_')]
class TarifController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/tarif/index.html.twig');
    }

    #[Route('/new', name: 'new')]
    public function new(): Response
    {
        return $this->render('admin/tarif/new.html.twig');
    }
}
