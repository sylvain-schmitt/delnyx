<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use App\Entity\ClientStatus;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Nom du client'
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Prénom du client'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'email@exemple.com'
                ]
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
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
