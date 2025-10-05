<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use App\Entity\ClientStatus;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Nom du client'
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prénom est obligatoire.']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Prénom du client'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'email est obligatoire.']),
                    new Assert\Email(['message' => 'L\'email {{ value }} n\'est pas valide.']),
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'L\'email ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'email@exemple.com'
                ]
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 20,
                        'maxMessage' => 'Le téléphone ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '06 12 34 56 78'
                ]
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Ville'
                ]
            ])
            ->add('siret', TextType::class, [
                'label' => 'SIRET',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'min' => 14,
                        'max' => 14,
                        'exactMessage' => 'Le SIRET doit contenir exactement {{ limit }} caractères.'
                    ])
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '12345678901234'
                ]
            ])
            ->add('adresse', TextareaType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'placeholder' => 'Adresse complète',
                    'rows' => 3
                ]
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code Postal',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '75001'
                ]
            ])
            ->add('pays', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'France'
                ]
            ])
            ->add('statut', EnumType::class, [
                'label' => 'Statut',
                'class' => ClientStatus::class,
                'choice_label' => function (ClientStatus $status) {
                    return $status->getLabel();
                },
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Les notes ne peuvent pas dépasser {{ limit }} caractères.'
                    ])
                ],
                'attr' => [
                    'class' => 'form-textarea',
                    'placeholder' => 'Notes sur le client',
                    'rows' => 4
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
