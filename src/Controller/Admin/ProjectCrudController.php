<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

class ProjectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Project::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Projet')
            ->setEntityLabelInPlural('Projets')
            ->setPageTitle('index', '📋 Gestion des Projets')
            ->setPageTitle('new', '➕ Nouveau Projet')
            ->setPageTitle('edit', '✏️ Modifier le Projet')
            ->setDefaultSort(['dateCreation' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('titre', 'Titre')
                ->setRequired(true)
                ->setHelp('Le titre du projet tel qu\'il apparaîtra sur le site'),

            TextEditorField::new('description', 'Description')
                ->setRequired(true)
                ->setHelp('Description détaillée du projet (HTML supporté)'),

            UrlField::new('url', 'URL du projet')
                ->setHelp('Lien vers le projet en ligne (optionnel)'),

            ChoiceField::new('statut', 'Statut')
                ->setChoices([
                    'Brouillon' => 'brouillon',
                    'Publié' => 'publie',
                    'Archivé' => 'archive'
                ])
                ->setRequired(true),

            AssociationField::new('technologies', 'Technologies')
                ->setFormTypeOptions([
                    'by_reference' => false,
                ])
                ->setHelp('Sélectionnez les technologies utilisées'),

            AssociationField::new('images', 'Images')
                ->onlyOnDetail()
                ->setTemplatePath('admin/project_images.html.twig'),

            DateTimeField::new('dateCreation', 'Date de création')
                ->hideOnForm(),

            DateTimeField::new('updatedAt', 'Dernière modification')
                ->hideOnForm(),
        ];
    }
}
