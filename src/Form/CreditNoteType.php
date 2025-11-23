<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use App\Entity\CompanySettings;
use App\EventSubscriber\RemoveEmptyLinesSubscriber;

class CreditNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $creditNote = $options['data'] ?? null;
        $isEdit = $creditNote && $creditNote->getId();
        $canEdit = !$isEdit || ($creditNote && $creditNote->canBeModified());

        $builder
            ->add('number', TextType::class, [
                'label' => 'Numéro de l\'avoir',
                'required' => false,
                'disabled' => true,
                'attr' => [
                    'class' => 'form-input',
                    'readonly' => true
                ],
                'help' => 'Le numéro sera généré automatiquement lors de la création (format: AV-YYYY-XXX)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('invoice', EntityType::class, [
                'label' => 'Facture associée',
                'class' => Invoice::class,
                'choice_label' => function (Invoice $invoice) {
                    return sprintf('%s - %s (%s)', 
                        $invoice->getNumero() ?? 'Facture #' . $invoice->getId(),
                        $invoice->getClient() ? $invoice->getClient()->getNomComplet() : 'Client inconnu',
                        $invoice->getMontantTTCFormate()
                    );
                },
                'required' => true,
                'placeholder' => 'Rechercher une facture émise...',
                'query_builder' => function (EntityRepository $er) use ($creditNote) {
                    $qb = $er->createQueryBuilder('i')
                        ->where('i.statut IN (:emitted)')
                        ->setParameter('emitted', [
                            InvoiceStatus::ISSUED->value,
                            InvoiceStatus::SENT->value,
                            InvoiceStatus::PAID->value,
                        ]);
                    
                    // Si on édite un avoir existant, permettre de voir la facture associée
                    if ($creditNote && $creditNote->getId() && $creditNote->getInvoice()) {
                        $qb->andWhere('i.id = :currentInvoiceId OR i.id = :currentInvoiceId')
                           ->setParameter('currentInvoiceId', $creditNote->getInvoice()->getId());
                    }
                    
                    return $qb->orderBy('i.dateCreation', 'DESC');
                },
                'attr' => ['class' => 'form-select'],
                'disabled' => !$canEdit,
                'help' => 'Un avoir ne peut être créé que pour une facture émise',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('statut', EnumType::class, [
                'label' => 'Statut',
                'class' => CreditNoteStatus::class,
                'choice_label' => fn(CreditNoteStatus $status) => $status->getLabel(),
                'property_path' => 'statutEnum',
                'attr' => ['class' => 'form-select'],
                'disabled' => !$canEdit,
                'help' => 'Statut de l\'avoir',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->addEventSubscriber(new RemoveEmptyLinesSubscriber())
            ->add('reason', TextareaType::class, [
                'label' => 'Motif',
                'required' => true,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 4
                ],
                'disabled' => !$canEdit,
                'help' => 'Raison de l\'avoir (obligatoire)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('lines', CollectionType::class, [
                'entry_type' => CreditNoteLineType::class,
                'entry_options' => [
                    'label' => false,
                    'credit_note' => $creditNote,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'disabled' => !$canEdit,
                'attr' => ['class' => 'credit-note-lines-collection']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreditNote::class,
            'company_settings' => null,
        ]);
        $resolver->setAllowedTypes('company_settings', ['null', CompanySettings::class]);
    }
}

