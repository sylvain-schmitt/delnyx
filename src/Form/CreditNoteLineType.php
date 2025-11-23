<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CreditNoteLine;
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

class CreditNoteLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Champ sourceLine en premier pour permettre de sélectionner la ligne à corriger
            // Champ sourceLine en premier pour permettre de sélectionner la ligne à corriger
            ->add('sourceLine', EntityType::class, [
                'label' => 'Ligne de la facture à corriger (optionnel)',
                'class' => InvoiceLine::class,
                'choice_label' => function (InvoiceLine $line) {
                    return sprintf('%s - %s × %s € = %s € HT', 
                        $line->getDescription(),
                        $line->getQuantity(),
                        number_format((float)$line->getUnitPrice(), 2, ',', ' '),
                        number_format((float)$line->getTotalHt(), 2, ',', ' ')
                    );
                },
                'required' => false,
                'placeholder' => 'Nouvelle ligne (pas de correction)',
                'query_builder' => function (EntityRepository $er) use ($options) {
                    // Retourner TOUTES les lignes InvoiceLine pour permettre la sélection de n'importe quelle ligne
                    // Cela permet de contourner les problèmes de validation lorsque la ligne n'est pas dans la facture initiale
                    return $er->createQueryBuilder('il')
                        ->orderBy('il.id', 'DESC');
                },
                // Permettre les choix qui ne sont pas dans le query_builder initial
                // (nécessaire car les lignes sont chargées dynamiquement via JavaScript)
                'choice_value' => function (?InvoiceLine $line) {
                    return $line ? (string)$line->getId() : null;
                },
                'attr' => ['class' => 'form-select'],
                'help' => 'Sélectionnez une ligne de la facture à corriger, ou laissez vide pour ajouter une nouvelle ligne',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
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
                'help' => 'Si un tarif est sélectionné, la description et le prix unitaire seront automatiquement pré-remplis.',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('description', TextType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-input'],
                'help' => 'Description de la ligne d\'avoir',
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
                'label' => 'Prix unitaire (€)',
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
                'help' => 'Taux de TVA pour cette ligne. Si non renseigné, le taux de la facture associée s\'applique.',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ]);

        // $builder->addEventSubscriber(new \App\Form\EventSubscriber\CreditNoteLineSourceLineSubscriber($this->entityManager));
    }

    public function __construct(
        private \Doctrine\ORM\EntityManagerInterface $entityManager
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreditNoteLine::class,
            'credit_note' => null,
        ]);
        $resolver->setAllowedTypes('credit_note', ['null', \App\Entity\CreditNote::class]);
    }
}

