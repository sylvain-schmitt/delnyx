<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\InvoiceLine;
use App\Entity\Tariff;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class InvoiceLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tariff', EntityType::class, [
                'label' => 'Tarif du catalogue',
                'class' => Tariff::class,
                'choice_label' => 'nom',
                'required' => false,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('t')
                        ->where('t.actif = :actif')
                        ->setParameter('actif', true)
                        ->orderBy('t.nom', 'ASC');
                },
                'attr' => ['class' => 'form-select'],
                'help' => 'Si un tarif est sélectionné, la description et le prix unitaire seront automatiquement pré-remplis. Sinon, créez une ligne personnalisée en renseignant manuellement ces informations.',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('description', TextType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-input'],
                'help' => 'Description de la prestation',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité',
                'required' => false,
                'attr' => ['class' => 'form-input', 'min' => 1],
                'help' => 'Quantité de la prestation',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('unitPrice', NumberType::class, [
                'label' => 'Prix unitaire HT',
                'required' => false,
                'scale' => 2,
                'attr' => ['class' => 'form-input', 'step' => '0.01', 'min' => 0],
                'help' => 'Prix unitaire en euros',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('tvaRate', ChoiceType::class, [
                'label' => 'Taux TVA (%)',
                'required' => false,
                'choices' => [
                    '0%' => '0.00',
                    '5,5%' => '5.50',
                    '10%' => '10.00',
                    '20%' => '20.00',
                ],
                'placeholder' => 'Taux global',
                'attr' => ['class' => 'form-select'],
                'help' => 'Taux de TVA pour cette ligne. Si non renseigné, le taux du devis associé s\'applique.',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoiceLine::class,
        ]);
    }
}

