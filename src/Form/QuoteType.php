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
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class QuoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'label' => 'Client',
                'class' => Client::class,
                'choice_label' => 'nomComplet',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.nom', 'ASC');
                },
                'attr' => ['class' => 'form-select'],
                'help' => 'Sélectionnez le client pour ce devis',
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
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'help' => 'Date jusqu\'à laquelle le devis est valide',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('tauxTVA', NumberType::class, [
                'label' => 'Taux de TVA (%)',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-input',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100'
                ],
                'help' => 'Taux de TVA en pourcentage (ex: 20.00)',
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
            ->add('sirenClient', TextType::class, [
                'label' => 'SIREN Client',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'maxlength' => 9,
                    'pattern' => '[0-9]{9}'
                ],
                'help' => 'SIREN du client (9 chiffres)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('adresseLivraison', TextareaType::class, [
                'label' => 'Adresse de livraison',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 3
                ],
                'help' => 'Adresse de livraison si différente de l\'adresse du client',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('paiementTvaSurDebits', CheckboxType::class, [
                'label' => 'Paiement TVA sur débits',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
                'help' => 'Cocher si le paiement de la TVA se fait sur les débits',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quote::class,
        ]);
    }
}

