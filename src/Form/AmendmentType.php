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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use App\EventSubscriber\RemoveEmptyLinesSubscriber;
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
                    return sprintf(
                        '%s - %s (%s)',
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
            ->addEventSubscriber(new RemoveEmptyLinesSubscriber())
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
                'entry_options' => [
                    'label' => false,
                    'amendment' => $amendment,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'disabled' => !$canEdit,
                'attr' => ['class' => 'amendment-lines-collection']
            ]);

        // Ajouter un listener pour mettre à jour le query_builder des lignes avant la validation
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            error_log('=== PRE_SUBMIT LISTENER APPELÉ ===');
            $data = $event->getData();
            $form = $event->getForm();
            error_log('PRE_SUBMIT - Données reçues: ' . json_encode(array_keys($data ?? [])));

            // Récupérer l'ID du devis depuis les données soumises
            $quoteId = null;
            if (isset($data['quote'])) {
                $quoteValue = $data['quote'];
                error_log('PRE_SUBMIT - quoteValue: ' . json_encode($quoteValue));

                if (is_numeric($quoteValue)) {
                    $quoteId = (int)$quoteValue;
                } elseif (is_string($quoteValue) && is_numeric($quoteValue)) {
                    $quoteId = (int)$quoteValue;
                }
            }
            error_log('PRE_SUBMIT - quoteId: ' . ($quoteId ?? 'null'));

            // Si on a un ID de devis, mettre à jour le query_builder du champ quote pour inclure ce devis
            // (nécessaire si le champ est désactivé ou si le query_builder initial ne le contient pas)
            if ($quoteId && $form->has('quote')) {
                error_log('PRE_SUBMIT - Mise à jour du champ quote');
                $quoteField = $form->get('quote');
                $quoteOptions = $quoteField->getConfig()->getOptions();

                // Mettre à jour le query_builder pour inclure le devis sélectionné
                $quoteOptions['query_builder'] = function (EntityRepository $er) use ($quoteId) {
                    $qb = $er->createQueryBuilder('q')
                        ->where('q.statut = :signed OR q.id = :quoteId')
                        ->setParameter('signed', QuoteStatus::SIGNED)
                        ->setParameter('quoteId', $quoteId);

                    return $qb->orderBy('q.dateCreation', 'DESC');
                };

                // IMPORTANT: Ne pas désactiver le champ lors de la reconstruction
                // Sinon Symfony ne traitera pas la valeur soumise
                $quoteOptions['disabled'] = false;

                // Reconstruire le champ quote avec le nouveau query_builder
                $form->remove('quote');
                $form->add('quote', EntityType::class, $quoteOptions);
            }

            // Si on a un ID de devis, mettre à jour le query_builder de toutes les lignes
            error_log('PRE_SUBMIT - Vérification: quoteId=' . ($quoteId ?? 'null') . ', has(lines)=' . ($form->has('lines') ? 'YES' : 'NO'));

            // Vérifier si des lignes sont dans les données soumises
            $linesData = $data['lines'] ?? [];
            error_log('PRE_SUBMIT - Nombre de lignes dans les données: ' . count($linesData));

            if ($quoteId && $form->has('lines')) {
                error_log('PRE_SUBMIT - Entrée dans le bloc de mise à jour des lignes');
                $linesForm = $form->get('lines');
                error_log('PRE_SUBMIT - Nombre de lignes dans le formulaire: ' . count($linesForm->all()));

                // Si des lignes sont dans les données mais pas dans le formulaire, les ajouter
                if (count($linesData) > count($linesForm->all())) {
                    error_log('PRE_SUBMIT - Ajout des lignes manquantes au formulaire');
                    $formOptions = $form->getConfig()->getOptions();
                    $amendment = $formOptions['data'] ?? null;
                    for ($i = count($linesForm->all()); $i < count($linesData); $i++) {
                        $linesForm->add((string)$i, AmendmentLineType::class, [
                            'label' => false,
                            'amendment' => $amendment,
                        ]);
                    }
                }

                error_log('PRE_SUBMIT - Nombre de lignes après ajout: ' . count($linesForm->all()));

                // Le ModelTransformer dans AmendmentLineType va gérer la conversion de l'ID en entité QuoteLine
                // Plus besoin de reconstruire le champ sourceLine ici
                error_log('PRE_SUBMIT - Les lignes ont été ajoutées, le ModelTransformer gérera la conversion');
            }

            error_log('=== FIN PRE_SUBMIT LISTENER ===');
        });
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
