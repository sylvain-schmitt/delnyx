<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Entity\Technology;
use App\Entity\ProjectImage;
use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Entity\Tarif;
use App\Entity\Avenant;
use App\Controller\Admin\ClientCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Redirection vers la liste des clients par défaut
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(ClientCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setFaviconPath('/images/favicon/favicon.ico')
            ->disableDarkMode()
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'monitor');

        yield MenuItem::section('👥 Gestion Commerciale');
        yield MenuItem::linkToCrud('Clients', 'users', Client::class);
        yield MenuItem::linkToCrud('Devis', 'file-text', Devis::class);
        yield MenuItem::linkToCrud('Factures', 'receipt', Facture::class);
        yield MenuItem::linkToCrud('Tarifs', 'calculator', Tarif::class);
        yield MenuItem::linkToCrud('Avenants', 'edit', Avenant::class);

        yield MenuItem::section('📁 Portfolio');
        yield MenuItem::linkToCrud('Projets', 'folder-open', Project::class);
        yield MenuItem::linkToCrud('Technologies', 'zap', Technology::class);
        yield MenuItem::linkToCrud('Images', 'image', ProjectImage::class);

        yield MenuItem::section('🌐 Site');
        yield MenuItem::linkToUrl('Voir le site', 'external-link', '/');
        yield MenuItem::linkToUrl('API Platform', 'database', '/api');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('admin')
            ->useCustomIconSet('lucide');
    }
}
