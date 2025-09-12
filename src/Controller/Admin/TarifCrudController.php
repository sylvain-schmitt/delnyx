<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tarif;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

class TarifCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tarif::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tarif')
            ->setEntityLabelInPlural('Tarifs')
            ->setSearchFields(['nom', 'categorie', 'description'])
            ->setHelp('index', 'Gérez vos tarifs et suivez leur statut de disponibilité. Les tarifs peuvent être créés depuis des devis acceptés.')
            ->setHelp('new', 'Créez un nouveau tarif. Les champs marqués d\'un astérisque (*) sont obligatoires.')
            ->setHelp('edit', 'Modifiez les informations du tarif. Les modifications sont automatiquement sauvegardées.')
            ->setDefaultSort(['categorie' => 'ASC', 'ordre' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->setPageTitle(Crud::PAGE_INDEX, 'Gérer les tarifs')
            ->setPageTitle(Crud::PAGE_NEW, 'Créer un tarif')
            ->setPageTitle(Crud::PAGE_EDIT, fn(Tarif $tarif) => sprintf('Modifier %s', $tarif->getNom()))
            ->setPageTitle(Crud::PAGE_DETAIL, fn(Tarif $tarif) => sprintf('Détails de %s', $tarif->getNom()))
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->setLabel('ID')
                ->hideOnForm()
                ->hideOnIndex(),

            TextField::new('nom')
                ->setLabel('Nom du tarif')
                ->setRequired(true)
                ->setHelp('Ex: "Pack Pro", "Module Réservation Standard"')
                ->setColumns(6),

            ChoiceField::new('categorie')
                ->setLabel('Catégorie')
                ->setRequired(true)
                ->setChoices(Tarif::getCategories())
                ->setHelp('Catégorie du service')
                ->setColumns(6),

            MoneyField::new('prix')
                ->setLabel('Prix')
                ->setCurrency('EUR')
                ->setRequired(true)
                ->setHelp('Prix du service')
                ->setColumns(4),

            ChoiceField::new('unite')
                ->setLabel('Unité')
                ->setRequired(true)
                ->setChoices(Tarif::getUnites())
                ->setHelp('Unité de facturation')
                ->setColumns(4),

            IntegerField::new('ordre')
                ->setLabel('Ordre')
                ->setHelp('Ordre d\'affichage (0 = premier)')
                ->setColumns(4),

            BooleanField::new('actif')
                ->setLabel('Actif')
                ->setHelp('Tarif disponible pour les devis')
                ->setColumns(6),

            TextareaField::new('description')
                ->setLabel('Description')
                ->setHelp('Description détaillée du service')
                ->setColumns(12)
                ->hideOnIndex(),

            TextareaField::new('caracteristiques')
                ->setLabel('Caractéristiques')
                ->setHelp('Liste des caractéristiques incluses (une par ligne)')
                ->setColumns(12)
                ->hideOnIndex(),

            DateTimeField::new('dateCreation')
                ->setLabel('Date de création')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setHelp('Date de création du tarif')
                ->hideOnForm(),

            DateTimeField::new('dateModification')
                ->setLabel('Dernière modification')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setHelp('Date de dernière modification')
                ->hideOnForm()
                ->hideOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DETAIL, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('categorie', 'Catégorie')
                ->setChoices(Tarif::getCategories()))
            ->add(ChoiceFilter::new('unite', 'Unité')
                ->setChoices(Tarif::getUnites()))
            ->add(BooleanFilter::new('actif', 'Actif'))
            ->add(NumericFilter::new('prix', 'Prix'));
    }
}
