<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ReminderRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReminderRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la règle',
                'attr' => [
                    'placeholder' => 'Ex: 1ère relance, Relance urgente...',
                    'class' => 'form-input',
                ],
                'help' => 'Un nom descriptif pour identifier cette règle',
                'help_attr' => ['class' => 'text-white/60 text-sm mt-1'],
                'empty_data' => '',
            ])
            ->add('daysAfterDue', IntegerType::class, [
                'label' => 'Jours après échéance',
                'attr' => [
                    'placeholder' => '7',
                    'class' => 'form-input',
                    'min' => 0,
                    'max' => 365,
                ],
                'help' => 'Nombre de jours après la date d\'échéance pour envoyer la relance (0 = le jour même)',
                'help_attr' => ['class' => 'text-white/60 text-sm mt-1'],
                'empty_data' => 7,
            ])
            ->add('maxReminders', IntegerType::class, [
                'label' => 'Nombre maximum de relances',
                'attr' => [
                    'class' => 'form-input',
                    'min' => 1,
                    'max' => 10,
                ],
                'help' => 'Nombre maximum de relances envoyées (toutes règles confondues)',
                'help_attr' => ['class' => 'text-white/60 text-sm mt-1'],
                'empty_data' => 3,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Règle active',
                'required' => false,
                'attr' => [
                    'class' => 'form-checkbox',
                ],
                'label_attr' => [
                    'class' => 'text-white/90 cursor-pointer',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ReminderRule::class,
        ]);
    }
}
