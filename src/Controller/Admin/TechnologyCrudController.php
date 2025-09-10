<?php

namespace App\Controller\Admin;

use App\Entity\Technology;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class TechnologyCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Technology::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Technologie')
            ->setEntityLabelInPlural('Technologies')
            ->setPageTitle('index', '🔧 Gestion des Technologies')
            ->setPageTitle('new', '➕ Nouvelle Technologie')
            ->setPageTitle('edit', '✏️ Modifier la Technologie')
            ->setDefaultSort(['nom' => 'ASC'])
            ->setPaginatorPageSize(50);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            TextField::new('nom', 'Nom')
                ->setRequired(true)
                ->setHelp('Nom de la technologie (ex: Symfony, React, PostgreSQL)'),

            ColorField::new('couleur', 'Couleur')
                ->setRequired(true)
                ->setHelp('Couleur associée à la technologie (format hexadécimal)'),

            TextField::new('icone', 'Icône')
                ->setHelp('Nom complet de l\'icône avec préfixe (ex: skill-icons:symfony-light, lucide:shield, logos:react). Laissez vide si pas d\'icône.')
                ->setFormTypeOption('attr', [
                    'placeholder' => 'skill-icons:symfony-light',
                    'list' => 'icon-suggestions'
                ])
                ->setFormTypeOption('required', false),

            DateTimeField::new('createdAt', 'Date de création')
                ->hideOnForm(),

            DateTimeField::new('updatedAt', 'Dernière modification')
                ->hideOnForm(),
        ];
    }
}
