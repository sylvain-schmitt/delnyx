<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Tariff;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TariffType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du tarif',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: Site vitrine Premium'],
                'help' => 'Nom de la prestation qui apparaîtra sur les devis',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => array_flip(Tariff::getCategories()),
                'placeholder' => 'Sélectionnez une catégorie',
                'attr' => ['class' => 'form-select'],
                'help' => 'Type de prestation',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-input', 'rows' => 3, 'placeholder' => 'Description détaillée de la prestation...'],
                'help' => 'Description complète visible sur le devis',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix HT (€)',
                'scale' => 2,
                'attr' => ['class' => 'form-input', 'step' => '0.01', 'min' => 0, 'placeholder' => '0.00'],
                'help' => 'Prix hors taxes en euros',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('unite', ChoiceType::class, [
                'label' => 'Unité de facturation',
                'choices' => array_flip(Tariff::getUnites()),
                'placeholder' => 'Sélectionnez une unité',
                'attr' => ['class' => 'form-select'],
                'help' => 'Mode de facturation (forfait, par mois, etc.)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('caracteristiques', TextareaType::class, [
                'label' => 'Caractéristiques',
                'required' => false,
                'attr' => ['class' => 'form-input', 'rows' => 4, 'placeholder' => "- Caractéristique 1\n- Caractéristique 2\n- Caractéristique 3"],
                'help' => 'Liste des fonctionnalités incluses (une par ligne)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => ['class' => 'form-input', 'min' => 0],
                'help' => 'Les tarifs sont triés par ordre croissant',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
                'help' => 'Seuls les tarifs actifs sont disponibles lors de la création de devis',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tariff::class,
        ]);
    }
}
