<?php

namespace App\Controller\Admin;

use App\Entity\Devis;
use App\Entity\DevisStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Doctrine\ORM\EntityManagerInterface;


class DevisCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return Devis::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Devis')
            ->setEntityLabelInPlural('Devis')
            ->setPageTitle('index', 'Liste des devis')
            ->setPageTitle('detail', 'Détails du devis')
            ->setPageTitle('new', 'Créer un nouveau devis')
            ->setPageTitle('edit', 'Modifier le devis')
            ->setDefaultSort(['dateCreation' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setHelp('index', 'Gérez vos devis et suivez leur statut. Les devis acceptés peuvent être transformés en factures.');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')
                ->onlyOnDetail(),

            TextField::new('numero', 'Numéro')
                ->setHelp('Numéro unique du devis (auto-généré)')
                ->setRequired(true)
                ->hideOnForm(),

            AssociationField::new('client', 'Client')
                ->setHelp('Sélectionnez le client pour ce devis')
                ->setRequired(true)
                ->formatValue(function ($value, $entity) {
                    return $entity->getClient()?->getNomComplet();
                }),

            AssociationField::new('tarifs', 'Tarifs')
                ->setHelp('Sélectionnez un ou plusieurs tarifs (les montants seront calculés automatiquement)')
                ->setRequired(false)
                ->setFormTypeOption('multiple', true)
                ->hideOnIndex() // Masquer dans la liste pour le responsive
                ->formatValue(function ($value, $entity) {
                    $tarifs = $entity->getTarifs();
                    if ($tarifs->isEmpty()) {
                        return 'Aucun tarif sélectionné';
                    }
                    $noms = [];
                    foreach ($tarifs as $tarif) {
                        $noms[] = $tarif->getNom() . ' (' . $tarif->getPrixFormate() . ')';
                    }
                    return implode(', ', $noms);
                }),

            DateTimeField::new('dateCreation', 'Date de création')
                ->setHelp('Date de création du devis')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnForm(),

            DateTimeField::new('dateValidite', 'Date de validité')
                ->setHelp('Date limite de validité du devis')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setRequired(false),

            ChoiceField::new('statut', 'Statut')
                ->setHelp('Statut actuel du devis')
                ->setChoices(DevisStatus::getEasyAdminChoices())
                ->formatValue(function ($value, $entity) {
                    if (!$entity->getStatut()) {
                        return 'Non défini';
                    }
                    return $entity->getStatut()->getLabel();
                }),

            ChoiceField::new('tauxTVA', 'Taux TVA')
                ->setHelp('Taux de TVA (0% pour micro-entrepreneur non assujetti)')
                ->setRequired(true)
                ->setChoices([
                    '0% (Micro-entrepreneur)' => '0.00',
                    '5.5%' => '5.50',
                    '10%' => '10.00',
                    '20%' => '20.00'
                ]),

            MoneyField::new('montantHT', 'Montant HT')
                ->setHelp('Montant hors taxes (calculé automatiquement depuis le tarif)')
                ->setCurrency('EUR')
                ->setRequired(true)
                ->setFormTypeOption('disabled', true)
                ->hideOnForm(),

            MoneyField::new('montantTTC', 'Montant TTC')
                ->setHelp('Montant toutes taxes comprises (calculé automatiquement)')
                ->setCurrency('EUR')
                ->setRequired(true)
                ->setFormTypeOption('disabled', true)
                ->hideOnForm(),

            NumberField::new('acomptePourcentage', 'Acompte (%)')
                ->setHelp('Pourcentage d\'acompte demandé (ex: 30 pour 30%)')
                ->setRequired(true)
                ->setNumDecimals(2),

            MoneyField::new('montantAcompte', 'Montant acompte')
                ->setHelp('Montant de l\'acompte calculé automatiquement')
                ->setCurrency('EUR')
                ->onlyOnIndex()
                ->formatValue(function ($value, $entity) {
                    return number_format($entity->getMontantAcompte(), 2) . ' €';
                }),

            TextareaField::new('conditionsPaiement', 'Conditions de paiement')
                ->setHelp('Conditions de paiement spécifiques')
                ->setRequired(false)
                ->hideOnIndex(),

            TextField::new('delaiLivraison', 'Délai de livraison')
                ->setHelp('Délai de livraison ou de réalisation')
                ->setRequired(false)
                ->hideOnIndex(),

            TextareaField::new('notes', 'Notes')
                ->setHelp('Notes et observations internes')
                ->setRequired(false)
                ->hideOnIndex(),

            DateTimeField::new('dateAcceptation', 'Date d\'acceptation')
                ->setHelp('Date d\'acceptation du devis par le client')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnForm()
                ->hideOnIndex(),

            // ===== NOUVELLES MENTIONS OBLIGATOIRES (2026-2027) =====

            TextField::new('sirenClient', 'SIREN Client')
                ->setHelp('Numéro SIREN du client (obligatoire à partir de 2026)')
                ->setRequired(false)
                ->hideOnIndex(),

            TextareaField::new('adresseLivraison', 'Adresse de livraison')
                ->setHelp('Adresse de livraison si différente de l\'adresse de facturation')
                ->setRequired(false)
                ->hideOnIndex(),

            ChoiceField::new('typeOperations', 'Type d\'opérations')
                ->setHelp('Type d\'opérations (obligatoire à partir de 2026)')
                ->setChoices([
                    'Prestations de services uniquement' => 'services',
                    'Livraisons de biens uniquement' => 'biens',
                    'Biens et services' => 'mixte'
                ])
                ->setRequired(true)
                ->hideOnIndex(),

            BooleanField::new('paiementTvaSurDebits', 'TVA sur débits')
                ->setHelp('Paiement de la TVA sur les débits (obligatoire à partir de 2026)')
                ->hideOnIndex(),

            DateTimeField::new('dateModification', 'Dernière modification')
                ->setHelp('Date de dernière modification')
                ->setFormat('dd/MM/yyyy HH:mm')
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
            ->add(ChoiceFilter::new('statut', 'Statut')
                ->setChoices(DevisStatus::getChoices()))
            ->add(DateTimeFilter::new('dateCreation', 'Date de création'))
            ->add(DateTimeFilter::new('dateValidite', 'Date de validité'))
            ->add(EntityFilter::new('client', 'Client'))
            ->add(NumericFilter::new('montantTTC', 'Montant TTC'))
            ->add(ChoiceFilter::new('typeOperations', 'Type d\'opérations')
                ->setChoices([
                    'Prestations de services uniquement' => 'services',
                    'Livraisons de biens uniquement' => 'biens',
                    'Biens et services' => 'mixte'
                ]));
    }

    public function createEntity(string $entityFqcn)
    {
        $devis = new Devis();

        // Générer un numéro de devis automatique
        $devis->setNumero($this->generateDevisNumber());

        // Définir la date de validité par défaut (30 jours)
        $dateValidite = new \DateTime();
        $dateValidite->modify('+30 days');
        $devis->setDateValidite($dateValidite);

        // TVA par défaut à 0% pour micro-entrepreneur
        $devis->setTauxTVA('0.00');

        return $devis;
    }

    public function persistEntity($entityManager, $entityInstance): void
    {
        // Recalculer les montants avant la sauvegarde
        if ($entityInstance instanceof Devis && !$entityInstance->getTarifs()->isEmpty()) {
            $entityInstance->calculerMontantsDepuisTarifs();
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity($entityManager, $entityInstance): void
    {
        // Recalculer les montants avant la mise à jour
        if ($entityInstance instanceof Devis && !$entityInstance->getTarifs()->isEmpty()) {
            $entityInstance->calculerMontantsDepuisTarifs();
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Génère un numéro de devis automatique
     */
    private function generateDevisNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        // Compter les devis de l'année en cours
        $count = $this->entityManager->getRepository(Devis::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.numero LIKE :pattern')
            ->setParameter('pattern', "DEV-{$year}-{$month}-%")
            ->getQuery()
            ->getSingleScalarResult();

        $nextNumber = $count + 1;
        return sprintf('DEV-%s-%s-%03d', $year, $month, $nextNumber);
    }
}
