<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use App\Entity\Quote;
use App\Entity\QuoteStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use App\Entity\CompanySettings;

class AmendmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $amendment = $options['data'] ?? null;
        $isEdit = $amendment && $amendment->getId();
        $canEdit = !$isEdit || ($amendment && $amendment->canBeModified());

        $builder
            ->add('numero', TextType::class, [
                'label' => 'Numéro de l\'avenant',
                'required' => false,
                'disabled' => true,
                'attr' => [
                    'class' => 'form-input',
                    'readonly' => true
                ],
                'help' => 'Le numéro sera généré automatiquement lors de la création (format: YYYY-XXX-A1)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('quote', EntityType::class, [
                'label' => 'Devis associé',
                'class' => Quote::class,
                'choice_label' => function (Quote $quote) {
                    return sprintf('%s - %s (%s)', 
                        $quote->getNumero() ?? 'Devis #' . $quote->getId(),
                        $quote->getClient() ? $quote->getClient()->getNomComplet() : 'Client inconnu',
                        $quote->getMontantTTCFormate()
                    );
                },
                'required' => true,
                'placeholder' => 'Rechercher un devis signé...',
                'query_builder' => function (EntityRepository $er) use ($amendment) {
                    $qb = $er->createQueryBuilder('q')
                        ->where('q.statut = :signed')
                        ->setParameter('signed', QuoteStatus::SIGNED);
                    
                    // Si on édite un avenant existant, permettre de voir le devis associé
                    if ($amendment && $amendment->getId() && $amendment->getQuote()) {
                        $qb->andWhere('q.id = :currentQuoteId OR q.id = :currentQuoteId')
                           ->setParameter('currentQuoteId', $amendment->getQuote()->getId());
                    }
                    
                    return $qb->orderBy('q.dateCreation', 'DESC');
                },
                'attr' => ['class' => 'form-select'],
                'disabled' => !$canEdit,
                'help' => 'Un avenant ne peut être créé que pour un devis signé',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('statut', EnumType::class, [
                'label' => 'Statut',
                'class' => AmendmentStatus::class,
                'choice_label' => fn(AmendmentStatus $status) => $status->getLabel(),
                'property_path' => 'statutEnum',
                'attr' => ['class' => 'form-select'],
                'disabled' => !$canEdit,
                'help' => 'Statut de l\'avenant',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif',
                'required' => true,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 3
                ],
                'disabled' => !$canEdit,
                'help' => 'Raison de la modification',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('modifications', TextareaType::class, [
                'label' => 'Description des modifications',
                'required' => true,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 4
                ],
                'disabled' => !$canEdit,
                'help' => 'Détail des modifications apportées au devis',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('justification', TextareaType::class, [
                'label' => 'Justification',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 3
                ],
                'disabled' => !$canEdit,
                'help' => 'Justification de la modification (optionnel)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('tauxTVA', NumberType::class, [
                'label' => 'Taux TVA (%)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-input',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100'
                ],
                'disabled' => !$canEdit,
                'help' => 'Taux de TVA global (sera pré-rempli depuis le devis)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 3
                ],
                'disabled' => !$canEdit,
                'help' => 'Notes internes (non visibles sur le document)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('lines', CollectionType::class, [
                'entry_type' => AmendmentLineType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'disabled' => !$canEdit,
                'attr' => ['class' => 'amendment-lines-collection']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Amendment::class,
            'company_settings' => null,
        ]);
        $resolver->setAllowedTypes('company_settings', ['null', CompanySettings::class]);
    }
}

