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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PdfGeneratorService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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
            ->setHelp('index', 'Gérez les avenants pour modifier les devis et factures émis. Un avenant doit être validé pour être appliqué.');
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

            // Champ pour sélectionner le devis
            AssociationField::new('devis', 'Devis à modifier')
                ->setHelp('Sélectionnez le devis à modifier (si applicable)')
                ->setRequired(false)
                ->hideOnIndex()
                ->formatValue(function ($value, $entity) {
                    return $entity->getDevis()?->getNumero() . ' - ' . $entity->getDevis()?->getClient()?->getNomComplet();
                }),

            // Champ pour sélectionner la facture
            AssociationField::new('facture', 'Facture à modifier')
                ->setHelp('Sélectionnez la facture à modifier (si applicable)')
                ->setRequired(false)
                ->hideOnIndex()
                ->formatValue(function ($value, $entity) {
                    return $entity->getFacture()?->getNumero() . ' - ' . $entity->getFacture()?->getClient()?->getNomComplet();
                }),

            // Affichage du document sélectionné
            TextField::new('documentInfo', 'Document concerné')
                ->setHelp('Document concerné par l\'avenant')
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    return $entity->getDocumentInfo();
                }),

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
                    'Rejeté' => 'rejete'
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
                return $entity && $entity->isValide();
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
            ->add(ChoiceFilter::new('typeDocument', 'Type de document')
                ->setChoices([
                    'Devis' => 'devis',
                    'Facture' => 'facture'
                ]))
            ->add(ChoiceFilter::new('statut', 'Statut')
                ->setChoices([
                    'Brouillon' => 'brouillon',
                    'Validé' => 'valide',
                    'Envoyé' => 'envoye',
                    'Rejeté' => 'rejete'
                ]))
            ->add(DateTimeFilter::new('dateCreation', 'Date de création'))
            ->add(DateTimeFilter::new('dateValidation', 'Date de validation'))
            ->add(TextFilter::new('documentNumero', 'Numéro du document'));
    }

    public function createEntity(string $entityFqcn)
    {
        $avenant = new Avenant();

        // Générer un numéro d'avenant automatique
        $avenant->setNumero($this->generateAvenantNumber());

        return $avenant;
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
