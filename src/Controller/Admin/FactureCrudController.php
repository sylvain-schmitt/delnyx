<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Facture;
use App\Entity\FactureStatus;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Doctrine\ORM\EntityManagerInterface;

class FactureCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return Facture::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Facture')
            ->setEntityLabelInPlural('Factures')
            ->setPageTitle('index', 'Liste des factures')
            ->setPageTitle('detail', 'Détails de la facture')
            ->setPageTitle('new', 'Créer une nouvelle facture')
            ->setPageTitle('edit', 'Modifier la facture')
            ->setDefaultSort(['dateCreation' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setHelp('index', 'Gérez vos factures et suivez leur statut de paiement. Les factures peuvent être créées depuis des devis acceptés.');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')
                ->onlyOnDetail(),

            TextField::new('numero', 'Numéro')
                ->setHelp('Numéro unique de la facture (auto-généré)')
                ->setRequired(true)
                ->hideOnForm(),

            AssociationField::new('client', 'Client')
                ->setHelp('Client de la facture')
                ->setRequired(true)
                ->formatValue(function ($value, $entity) {
                    return $entity->getClient()?->getNomComplet();
                }),

            AssociationField::new('devis', 'Devis associé')
                ->setHelp('Devis à l\'origine de cette facture')
                ->setRequired(true)
                ->hideOnIndex()
                ->formatValue(function ($value, $entity) {
                    return $entity->getDevis()?->getNumero();
                }),

            DateTimeField::new('dateCreation', 'Date de création')
                ->setHelp('Date de création de la facture')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnForm(),

            DateTimeField::new('dateEcheance', 'Date d\'échéance')
                ->setHelp('Date limite de paiement')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setRequired(true),

            ChoiceField::new('statut', 'Statut')
                ->setHelp('Statut actuel de la facture')
                ->setChoices(FactureStatus::getEasyAdminChoices())
                ->formatValue(function ($value, $entity) {
                    return $entity->getStatutLabel();
                }),

            MoneyField::new('montantHT', 'Montant HT')
                ->setHelp('Montant hors taxes (calculé automatiquement depuis le devis)')
                ->setCurrency('EUR')
                ->setRequired(false)
                ->setFormTypeOption('disabled', true)
                ->hideOnForm(),

            MoneyField::new('montantTVA', 'Montant TVA')
                ->setHelp('Montant de la TVA (calculé automatiquement)')
                ->setCurrency('EUR')
                ->setRequired(false)
                ->setFormTypeOption('disabled', true)
                ->hideOnForm(),

            MoneyField::new('montantTTC', 'Montant TTC')
                ->setHelp('Montant toutes taxes comprises (calculé automatiquement)')
                ->setCurrency('EUR')
                ->setRequired(false)
                ->setFormTypeOption('disabled', true)
                ->hideOnForm(),

            MoneyField::new('montantAcompte', 'Montant acompte')
                ->setHelp('Montant de l\'acompte déjà reçu')
                ->setCurrency('EUR')
                ->setRequired(false)
                ->hideOnForm(),

            TextField::new('montantRestant', 'Montant restant')
                ->setHelp('Montant restant à payer')
                ->onlyOnIndex()
                ->formatValue(function ($value, $entity) {
                    return $entity->getMontantRestantFormate();
                }),

            TextareaField::new('conditionsPaiement', 'Conditions de paiement')
                ->setHelp('Conditions de paiement spécifiques')
                ->setRequired(false)
                ->hideOnIndex(),

            IntegerField::new('delaiPaiement', 'Délai de paiement (jours)')
                ->setHelp('Délai de paiement en jours')
                ->setRequired(false)
                ->hideOnIndex(),

            NumberField::new('penalitesRetard', 'Pénalités de retard (%)')
                ->setHelp('Taux de pénalités par jour de retard')
                ->setRequired(false)
                ->setNumDecimals(2)
                ->hideOnIndex(),

            TextareaField::new('notes', 'Notes')
                ->setHelp('Notes et observations internes')
                ->setRequired(false)
                ->hideOnIndex(),

            DateTimeField::new('datePaiement', 'Date de paiement')
                ->setHelp('Date de paiement effectif')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnForm()
                ->hideOnIndex(),

            DateTimeField::new('dateEnvoi', 'Date d\'envoi')
                ->setHelp('Date d\'envoi au client')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnForm()
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
                ->setChoices(FactureStatus::getChoices()))
            ->add(DateTimeFilter::new('dateCreation', 'Date de création'))
            ->add(DateTimeFilter::new('dateEcheance', 'Date d\'échéance'))
            ->add(DateTimeFilter::new('datePaiement', 'Date de paiement'))
            ->add(EntityFilter::new('client', 'Client'))
            ->add(EntityFilter::new('devis', 'Devis'))
            ->add(NumericFilter::new('montantTTC', 'Montant TTC'));
    }

    public function createEntity(string $entityFqcn)
    {
        $facture = new Facture();

        // Générer un numéro de facture automatique
        $facture->setNumero($this->generateFactureNumber());

        // Définir la date d'échéance par défaut (30 jours)
        $dateEcheance = new \DateTime();
        $dateEcheance->modify('+30 days');
        $facture->setDateEcheance($dateEcheance);

        // Délai de paiement par défaut
        $facture->setDelaiPaiement(30);

        // Pénalités de retard par défaut (0.1% par jour)
        $facture->setPenalitesRetard('0.10');

        return $facture;
    }

    public function persistEntity($entityManager, $entityInstance): void
    {
        // Copier les montants depuis le devis associé si disponible
        if ($entityInstance instanceof Facture && $entityInstance->getDevis()) {
            $devis = $entityInstance->getDevis();

            // Copier les montants stockés en centimes
            $entityInstance->setMontantHT($devis->getMontantHT());
            $entityInstance->setMontantTTC($devis->getMontantTTC());

            // Calculer et copier la TVA en centimes
            $montantTTC = (float) $devis->getMontantTTC();
            $montantHT = (float) $devis->getMontantHT();
            $montantTVA = $montantTTC - $montantHT;
            $entityInstance->setMontantTVA(number_format($montantTVA, 0, '.', ''));

            // Calculer et copier l'acompte en centimes
            $montantTTCEnEuros = $montantTTC / 100;
            $acomptePourcentage = (float) $devis->getAcomptePourcentage();
            $montantAcompteEnEuros = $montantTTCEnEuros * ($acomptePourcentage / 100);
            $montantAcompteEnCentimes = $montantAcompteEnEuros * 100;
            $entityInstance->setMontantAcompte(number_format($montantAcompteEnCentimes, 0, '.', ''));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity($entityManager, $entityInstance): void
    {
        // Copier les montants depuis le devis associé si disponible
        if ($entityInstance instanceof Facture && $entityInstance->getDevis()) {
            $devis = $entityInstance->getDevis();

            // Copier les montants stockés en centimes
            $entityInstance->setMontantHT($devis->getMontantHT());
            $entityInstance->setMontantTTC($devis->getMontantTTC());

            // Calculer et copier la TVA en centimes
            $montantTTC = (float) $devis->getMontantTTC();
            $montantHT = (float) $devis->getMontantHT();
            $montantTVA = $montantTTC - $montantHT;
            $entityInstance->setMontantTVA(number_format($montantTVA, 0, '.', ''));

            // Calculer et copier l'acompte en centimes
            $montantTTCEnEuros = $montantTTC / 100;
            $acomptePourcentage = (float) $devis->getAcomptePourcentage();
            $montantAcompteEnEuros = $montantTTCEnEuros * ($acomptePourcentage / 100);
            $montantAcompteEnCentimes = $montantAcompteEnEuros * 100;
            $entityInstance->setMontantAcompte(number_format($montantAcompteEnCentimes, 0, '.', ''));
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Génère un numéro de facture automatique
     */
    private function generateFactureNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        // Compter les factures de l'année en cours
        $count = $this->entityManager->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.numero LIKE :pattern')
            ->setParameter('pattern', "FAC-{$year}-{$month}-%")
            ->getQuery()
            ->getSingleScalarResult();

        $nextNumber = $count + 1;
        return sprintf('FAC-%s-%s-%03d', $year, $month, $nextNumber);
    }
}
