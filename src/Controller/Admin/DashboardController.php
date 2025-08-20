<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Entity\Technology;
use App\Entity\ProjectImage;
use App\Controller\Admin\ProjectCrudController;
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
        // Redirection vers la liste des projets par défaut
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(ProjectCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('🚀 Delnyx - Administration')
            ->setFaviconPath('/images/favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('🏠 Dashboard', 'fa fa-home');

        yield MenuItem::section('📁 Portfolio');
        yield MenuItem::linkToCrud('📋 Projets', 'fas fa-project-diagram', Project::class);
        yield MenuItem::linkToCrud('🔧 Technologies', 'fas fa-cogs', Technology::class);
        yield MenuItem::linkToCrud('🖼️ Images', 'fas fa-images', ProjectImage::class);

        yield MenuItem::section('🌐 Site');
        yield MenuItem::linkToUrl('🚀 Voir le site', 'fas fa-external-link-alt', '/');
        yield MenuItem::linkToUrl('📊 API Platform', 'fas fa-code', '/api');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('css/admin.css');
    }
}
