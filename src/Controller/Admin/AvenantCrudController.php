<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Avenant;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PdfGeneratorService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;

class AvenantCrudController extends AbstractCrudController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PdfGeneratorService $pdfGenerator
    ) {}

    public static function getEntityFqcn(): string
    {
        return Avenant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Avenant')
            ->setEntityLabelInPlural('Avenants')
            ->setPageTitle('index', 'Liste des avenants')
            ->setPageTitle('detail', 'Détails de l\'avenant')
            ->setPageTitle('new', 'Créer un nouvel avenant')
            ->setPageTitle('edit', 'Modifier l\'avenant')
            ->setDefaultSort(['dateCreation' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setHelp('index', 'Gérez les avenants pour modifier les devis émis. Un avenant doit être validé pour être appliqué.');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id', 'ID')
                ->onlyOnDetail(),

            TextField::new('numero', 'Numéro')
                ->setHelp('Numéro unique de l\'avenant (auto-généré)')
                ->setRequired(true)
                ->hideOnForm(),

            // ===== RELATION OBLIGATOIRE AVEC UN DEVIS =====
            AssociationField::new('devis', 'Devis à modifier')
                ->setHelp('Sélectionnez le devis à modifier (OBLIGATOIRE)')
                ->setRequired(true)
                ->hideOnIndex()
                ->formatValue(function ($value, $entity) {
                    return $entity->getDevis()?->getNumero() . ' - ' . $entity->getDevis()?->getClient()?->getNomComplet();
                }),



            TextField::new('documentInfo', 'Devis concerné')
                ->setHelp('Devis concerné par l\'avenant')
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    return $entity->getDocumentInfo();
                }),

            // ===== SYSTÈME DE TARIFS POUR L'AVENANT =====
            AssociationField::new('tarifs', 'Tarifs de l\'avenant')
                ->setHelp('Sélectionnez les tarifs à ajouter/modifier dans l\'avenant (les montants seront calculés automatiquement)')
                ->setRequired(false)
                ->setFormTypeOption('multiple', true)
                ->hideOnIndex()
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

            // ===== MONTANTS CALCULÉS AUTOMATIQUEMENT =====
            MoneyField::new('montantHT', 'Montant HT')
                ->setHelp('Montant hors taxes (calculé automatiquement depuis les tarifs)')
                ->setCurrency('EUR')
                ->setRequired(true)
                ->setFormTypeOption('disabled', true)
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    return $entity->getMontantHTFormate();
                }),

            MoneyField::new('montantTVA', 'Montant TVA')
                ->setHelp('Montant de la TVA (calculé automatiquement)')
                ->setCurrency('EUR')
                ->setRequired(true)
                ->setFormTypeOption('disabled', true)
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    return $entity->getMontantTVAFormate();
                }),

            MoneyField::new('montantTTC', 'Montant TTC')
                ->setHelp('Montant toutes taxes comprises (calculé automatiquement)')
                ->setCurrency('EUR')
                ->setRequired(true)
                ->setFormTypeOption('disabled', true)
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    return $entity->getMontantTTCFormate();
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

            TextareaField::new('motif', 'Motif')
                ->setHelp('Raison de la modification')
                ->setRequired(true)
                ->setNumOfRows(3),

            TextareaField::new('modifications', 'Modifications')
                ->setHelp('Description détaillée des modifications à apporter')
                ->setRequired(true)
                ->setNumOfRows(5),

            TextareaField::new('justification', 'Justification')
                ->setHelp('Justification comptable de la modification')
                ->setRequired(false)
                ->setNumOfRows(3)
                ->hideOnIndex(),

            DateTimeField::new('dateCreation', 'Date de création')
                ->setHelp('Date de création de l\'avenant')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnForm(),

            DateTimeField::new('dateValidation', 'Date de validation')
                ->setHelp('Date de validation de l\'avenant')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->hideOnForm(),

            ChoiceField::new('statut', 'Statut')
                ->setHelp('Statut de l\'avenant')
                ->setRequired(true)
                ->setChoices([
                    'Brouillon' => 'brouillon',
                    'Validé' => 'valide',
                    'Rejeté' => 'rejete',
                    'Envoyé' => 'envoye'
                ])
                ->formatValue(function ($value, $entity) {
                    return $entity->getStatutLabel();
                }),

            TextareaField::new('notes', 'Notes')
                ->setHelp('Notes et observations internes')
                ->setRequired(false)
                ->setNumOfRows(3)
                ->hideOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        // Actions pour valider/rejeter/mettre envoyé rapidement
        $validate = Action::new('validate', '✅ Valider')
            ->linkToCrudAction('validate')
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(function ($entity) {
                return $entity && $entity->isBrouillon();
            });

        $reject = Action::new('reject', '❌ Rejeter')
            ->linkToCrudAction('reject')
            ->setCssClass('btn btn-danger btn-sm')
            ->displayIf(function ($entity) {
                return $entity && $entity->isBrouillon();
            });

        $markAsSent = Action::new('markAsSent', '📤 Envoyé')
            ->linkToCrudAction('markAsSent')
            ->setCssClass('btn btn-info btn-sm')
            ->displayIf(function ($entity) {
                return $entity && $entity->isBrouillon();
            });

        $generatePdf = Action::new('generatePdf', '📄 PDF')
            ->linkToCrudAction('generatePdf')
            ->setCssClass('btn btn-success');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $generatePdf)
            ->add(Crud::PAGE_INDEX, $validate)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_INDEX, $markAsSent)
            ->add(Crud::PAGE_DETAIL, $generatePdf)
            ->add(Crud::PAGE_DETAIL, $validate)
            ->add(Crud::PAGE_DETAIL, $reject)
            ->add(Crud::PAGE_DETAIL, $markAsSent)
            ->add(Crud::PAGE_EDIT, $generatePdf)
            ->add(Crud::PAGE_EDIT, $validate)
            ->add(Crud::PAGE_EDIT, $reject)
            ->add(Crud::PAGE_EDIT, $markAsSent)
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
            ->setPermission(Action::DETAIL, 'ROLE_ADMIN')
            ->setPermission('validate', 'ROLE_ADMIN')
            ->setPermission('reject', 'ROLE_ADMIN')
            ->setPermission('generatePdf', 'ROLE_ADMIN')
            ->setPermission('markAsSent', 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('statut', 'Statut')
                ->setChoices([
                    'Brouillon' => 'brouillon',
                    'Validé' => 'valide',
                    'Rejeté' => 'rejete',
                    'Envoyé' => 'envoye'
                ]))
            ->add(DateTimeFilter::new('dateCreation', 'Date de création'))
            ->add(DateTimeFilter::new('dateValidation', 'Date de validation'))
            ->add(EntityFilter::new('devis', 'Devis'));
    }

    public function createEntity(string $entityFqcn)
    {
        $avenant = new Avenant();

        // Générer un numéro d'avenant automatique
        $avenant->setNumero($this->generateAvenantNumber());

        return $avenant;
    }

    public function new(AdminContext $context)
    {
        $entity = $this->createEntity($context->getEntity()->getFqcn());

        // Si un devis est sélectionné via l'URL, propager son tauxTVA
        $devisId = $context->getRequest()->query->get('devis_id');
        if ($devisId) {
            $devis = $this->entityManager->getRepository(\App\Entity\Devis::class)->find($devisId);
            if ($devis) {
                $entity->setDevis($devis);
                $entity->setTauxTVA($devis->getTauxTVA());
            }
        }

        $context->getEntity()->setInstance($entity);

        return parent::new($context);
    }

    public function persistEntity($entityManager, $entityInstance): void
    {
        // Recalculer les montants avant la sauvegarde
        if ($entityInstance instanceof Avenant && !$entityInstance->getTarifs()->isEmpty()) {
            $entityInstance->calculerMontantsDepuisTarifs();
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity($entityManager, $entityInstance): void
    {
        // Recalculer les montants avant la mise à jour
        if ($entityInstance instanceof Avenant && !$entityInstance->getTarifs()->isEmpty()) {
            $entityInstance->calculerMontantsDepuisTarifs();
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Génère un numéro d'avenant automatique
     */
    private function generateAvenantNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        // Compter les avenants de l'année en cours
        $count = $this->entityManager->getRepository(Avenant::class)
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.numero LIKE :pattern')
            ->setParameter('pattern', "AV-{$year}-{$month}-%")
            ->getQuery()
            ->getSingleScalarResult();

        $nextNumber = $count + 1;
        return sprintf('AV-%s-%s-%03d', $year, $month, $nextNumber);
    }

    // (plus de helper nécessaire, l'AssociationField lit directement via la relation)

    /**
     * Génère le PDF de l'avenant
     */
    public function generatePdf(Request $request): Response
    {
        $id = $request->query->get('entityId');
        $avenant = $this->entityManager->getRepository(Avenant::class)->find($id);

        if (!$avenant) {
            throw $this->createNotFoundException('Avenant non trouvé');
        }

        return $this->pdfGenerator->generateAvenantPdf($avenant);
    }

    /**
     * Valide l'avenant
     */
    public function validate(Request $request): Response
    {
        $id = $request->query->get('entityId');
        $avenant = $this->entityManager->getRepository(Avenant::class)->find($id);

        if (!$avenant) {
            throw $this->createNotFoundException('Avenant non trouvé');
        }

        if (!$avenant->isBrouillon()) {
            $this->addFlash('error', 'Seuls les avenants en brouillon peuvent être validés');
            return $this->redirectToRoute('admin');
        }

        $avenant->setStatut('valide');
        $avenant->setDateValidation(new \DateTime());
        $this->entityManager->flush();

        $this->addFlash('success', 'Avenant validé avec succès');
        return $this->redirectToRoute('admin');
    }

    /**
     * Rejette l'avenant
     */
    public function reject(Request $request): Response
    {
        $id = $request->query->get('entityId');
        $avenant = $this->entityManager->getRepository(Avenant::class)->find($id);

        if (!$avenant) {
            throw $this->createNotFoundException('Avenant non trouvé');
        }

        if (!$avenant->isBrouillon()) {
            $this->addFlash('error', 'Seuls les avenants en brouillon peuvent être rejetés');
            return $this->redirectToRoute('admin');
        }

        $avenant->setStatut('rejete');
        $this->entityManager->flush();

        $this->addFlash('success', 'Avenant rejeté');
        return $this->redirectToRoute('admin');
    }

    /**
     * Marque l'avenant comme envoyé
     */
    public function markAsSent(Request $request): Response
    {
        $id = $request->query->get('entityId');
        $avenant = $this->entityManager->getRepository(Avenant::class)->find($id);

        if (!$avenant) {
            throw $this->createNotFoundException('Avenant non trouvé');
        }

        if (!$avenant->isValide()) {
            $this->addFlash('error', 'Seuls les avenants validés peuvent être marqués comme envoyés');
            return $this->redirectToRoute('admin');
        }

        $avenant->setStatut('envoye');
        $this->entityManager->flush();

        $this->addFlash('success', 'Avenant marqué comme envoyé');
        return $this->redirectToRoute('admin');
    }
}
