<?php

namespace App\Form;

use App\Entity\Technology;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TechnologyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la technologie',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Ex: Symfony, React, Vue.js...'
                ]
            ])
            ->add('couleur', ColorType::class, [
                'label' => 'Couleur',
                'attr' => [
                    'class' => 'form-input h-12 cursor-pointer'
                ]
            ])
            ->add('icone', TextType::class, [
                'label' => 'Icône (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Ex: lucide:code, skill-icons:symfony-light, logos:react...'
                ],
                'help' => 'Nom complet de l\'icône avec préfixe (ex: lucide:code, skill-icons:symfony-light). L\'importation se fait automatiquement à la sauvegarde.'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Technology::class,
        ]);
    }
}
