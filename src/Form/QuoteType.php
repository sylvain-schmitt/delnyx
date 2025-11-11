<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use App\Entity\CompanySettings;

class QuoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numero', TextType::class, [
                'label' => 'Numéro du devis',
                'required' => false,
                'disabled' => true,
                'attr' => [
                    'class' => 'form-input',
                    'readonly' => true
                ],
                'help' => 'Le numéro sera généré automatiquement lors de la création (format: DEV-YYYY-MM-XXX)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('client', EntityType::class, [
                'label' => 'Client',
                'class' => Client::class,
                'choice_label' => 'nomComplet',
                'required' => true,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.nom', 'ASC');
                },
                'attr' => ['class' => 'form-select', 'required' => 'required'],
                'help' => 'Tapez pour rechercher un client dans la liste',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('statut', EnumType::class, [
                'label' => 'Statut',
                'class' => QuoteStatus::class,
                'choice_label' => fn(QuoteStatus $status) => $status->getLabel(),
                'attr' => ['class' => 'form-select'],
                'help' => 'Statut du devis',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('dateValidite', DateType::class, [
                'label' => 'Date de validité',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input', 'required' => 'required'],
                'help' => 'Date jusqu\'à laquelle le devis est valide (par défaut : 30 jours, durée légale)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('usePerLineTva', CheckboxType::class, [
                'label' => 'Appliquer la TVA par ligne',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
                'help' => 'Si coché, chaque ligne peut avoir son propre taux de TVA. Sinon, le taux global de TVA (paramétré dans les paramètres de l\'entreprise) s\'applique à toutes les lignes.',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('acomptePourcentage', NumberType::class, [
                'label' => 'Pourcentage d\'acompte (%)',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-input',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100'
                ],
                'help' => 'Pourcentage d\'acompte demandé',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('conditionsPaiement', TextareaType::class, [
                'label' => 'Conditions de paiement',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 3
                ],
                'help' => 'Conditions de paiement (ex: 30 jours, à réception, etc.)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('delaiLivraison', TextType::class, [
                'label' => 'Délai de livraison',
                'required' => false,
                'attr' => ['class' => 'form-input'],
                'help' => 'Délai de livraison (ex: 2 semaines, 1 mois)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 4
                ],
                'help' => 'Notes internes (non visibles sur le devis)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('typeOperations', ChoiceType::class, [
                'label' => 'Type d\'opérations',
                'choices' => [
                    'Services' => 'services',
                    'Biens' => 'biens',
                    'Mixte' => 'mixte',
                ],
                'attr' => ['class' => 'form-select'],
                'help' => 'Type d\'opérations pour les mentions légales',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('paiementTvaSurDebits', CheckboxType::class, [
                'label' => 'Paiement TVA sur débits',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
                'help' => 'Cocher si votre entreprise paie la TVA sur les débits (facturation à l\'avance) plutôt que sur les encaissements. Cette option est utilisée pour la facturation électronique.',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('lines', CollectionType::class, [
                'entry_type' => QuoteLineType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'attr' => ['class' => 'quote-lines-collection']
            ]);

        // Retirer usePerLineTva si TVA désactivée
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $quote = $event->getData();
            $form = $event->getForm();

            if (!$quote) {
                return;
            }

            $companySettings = $options['company_settings'] ?? null;
            if ($companySettings && method_exists($companySettings, 'isTvaEnabled') && !$companySettings->isTvaEnabled()) {
                $form->remove('usePerLineTva');
            }
        });

        // Sécurité validation: si TVA désactivée à la soumission, retirer usePerLineTva
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $data = $event->getData();
            $form = $event->getForm();

            if (!$data) {
                return;
            }

            $companySettings = $options['company_settings'] ?? null;
            if ($companySettings && method_exists($companySettings, 'isTvaEnabled') && !$companySettings->isTvaEnabled()) {
                $form->remove('usePerLineTva');
                // Forcer usePerLineTva à false dans les données
                if (isset($data['usePerLineTva'])) {
                    unset($data['usePerLineTva']);
                }
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quote::class,
            'company_settings' => null,
        ]);
        $resolver->setAllowedTypes('company_settings', ['null', CompanySettings::class]);
    }
}

