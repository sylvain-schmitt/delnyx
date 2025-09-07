<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\ClientStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class ClientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Client::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Client')
            ->setEntityLabelInPlural('Clients')
            ->setDefaultSort(['dateCreation' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setSearchFields(['nom', 'prenom', 'email', 'telephone', 'ville'])
            ->setHelp('index', 'Gérez vos clients et prospects. Utilisez la recherche pour trouver rapidement un client.')
            ->setHelp('new', 'Créez un nouveau client. Les champs marqués d\'un astérisque (*) sont obligatoires.')
            ->setHelp('edit', 'Modifiez les informations du client. Les modifications sont automatiquement sauvegardées.');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex()
                ->setLabel('ID'),

            TextField::new('nom')
                ->setLabel('Nom')
                ->setRequired(true)
                ->setHelp('Nom de famille du client')
                ->setColumns(6),

            TextField::new('prenom')
                ->setLabel('Prénom')
                ->setRequired(true)
                ->setHelp('Prénom du client')
                ->setColumns(6),

            EmailField::new('email')
                ->setLabel('Email')
                ->setRequired(true)
                ->setHelp('Adresse email unique du client')
                ->setColumns(12),

            TelephoneField::new('telephone')
                ->setLabel('Téléphone')
                ->setHelp('Numéro de téléphone (optionnel)')
                ->setColumns(6),

            ChoiceField::new('statut')
                ->setLabel('Statut')
                ->setChoices(ClientStatus::getEasyAdminChoices())
                ->setRequired(true)
                ->setHelp('Statut du client dans votre base')
                ->setColumns(6)
                ->formatValue(function ($value, $entity) {
                    return $entity->getStatutLabel();
                }),

            TextField::new('adresse')
                ->setLabel('Adresse')
                ->setHelp('Adresse postale (optionnel)')
                ->setColumns(12)
                ->onlyOnForms(),

            TextField::new('codePostal')
                ->setLabel('Code postal')
                ->setHelp('Code postal (optionnel)')
                ->setColumns(4)
                ->onlyOnForms(),

            TextField::new('ville')
                ->setLabel('Ville')
                ->setHelp('Ville (optionnel)')
                ->setColumns(4)
                ->onlyOnForms(),

            TextField::new('pays')
                ->setLabel('Pays')
                ->setHelp('Pays (par défaut: France)')
                ->setColumns(4)
                ->onlyOnForms(),

            TextField::new('siret')
                ->setLabel('SIRET')
                ->setHelp('Numéro SIRET (14 chiffres, optionnel)')
                ->setColumns(6)
                ->onlyOnForms(),

            TextField::new('tvaIntracommunautaire')
                ->setLabel('TVA Intracommunautaire')
                ->setHelp('Numéro de TVA intracommunautaire (optionnel)')
                ->setColumns(6)
                ->onlyOnForms(),

            TextareaField::new('notes')
                ->setLabel('Notes')
                ->setHelp('Notes internes sur le client (optionnel)')
                ->setColumns(12)
                ->onlyOnForms(),

            DateTimeField::new('dateCreation')
                ->setLabel('Date de création')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->onlyOnIndex(),

            DateTimeField::new('dateModification')
                ->setLabel('Dernière modification')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->onlyOnIndex(),

            // Champs calculés pour l'index
            IntegerField::new('nombreDevis')
                ->setLabel('Devis')
                ->onlyOnIndex()
                ->setHelp('Nombre de devis'),

            IntegerField::new('nombreFactures')
                ->setLabel('Factures')
                ->onlyOnIndex()
                ->setHelp('Nombre de factures'),

            MoneyField::new('montantTotalFacture')
                ->setLabel('CA Total')
                ->setCurrency('EUR')
                ->onlyOnIndex()
                ->setHelp('Chiffre d\'affaires total'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('statut')
                ->setChoices(ClientStatus::getChoices())
                ->setLabel('Statut'))
            ->add(DateTimeFilter::new('dateCreation')
                ->setLabel('Date de création'))
            ->add(TextFilter::new('ville')
                ->setLabel('Ville'))
            ->add(TextFilter::new('pays')
                ->setLabel('Pays'));
    }
}
