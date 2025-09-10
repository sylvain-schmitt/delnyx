<?php

namespace App\Controller\Admin;

use App\Entity\ProjectImage;
use App\Entity\Project;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

class ProjectImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProjectImage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Image de projet')
            ->setEntityLabelInPlural('Images de projets')
            ->setPageTitle('index', 'Gestion des images de projets')
            ->setPageTitle('new', 'Ajouter une image')
            ->setPageTitle('edit', 'Modifier l\'image')
            ->setPageTitle('detail', 'Détails de l\'image')
            ->setDefaultSort(['projet' => 'ASC', 'ordre' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('projet', 'Projet')
            ->setFormTypeOption('choice_label', 'titre')
            ->setRequired(true);

        yield ImageField::new('fichier', 'Image')
            ->setBasePath('uploads/projects')
            ->setUploadDir('public/uploads/projects')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setRequired(true)
            ->setFormTypeOption('attr', [
                'accept' => 'image/*',
                'data-max-size' => '5MB'
            ]);

        yield TextField::new('altText', 'Texte alternatif')
            ->setHelp('Description de l\'image pour l\'accessibilité');

        yield IntegerField::new('ordre', 'Ordre d\'affichage')
            ->setHelp('Ordre d\'affichage des images (0 = premier)');

        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm();
    }
}
