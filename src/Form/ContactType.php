<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prénom est obligatoire'),
                    new Assert\Length(
                        min: 2,
                        max: 50,
                        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères'
                    ),
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Votre prénom',
                    'data-contact-target' => 'field',
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire'),
                    new Assert\Length(
                        min: 2,
                        max: 50,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
                    ),
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Votre nom',
                    'data-contact-target' => 'field',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'adresse email est obligatoire'),
                    new Assert\Email(message: 'Veuillez saisir une adresse email valide'),
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'votre@email.com',
                    'data-contact-target' => 'field',
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [
                    new Assert\Regex(
                        pattern: '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/',
                        message: 'Veuillez saisir un numéro de téléphone français valide'
                    ),
                ],
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => '06 12 34 56 78 (optionnel)',
                    'data-contact-target' => 'field',
                ],
            ])
            ->add('sujet', ChoiceType::class, [
                'label' => 'Sujet de votre demande',
                'choices' => [
                    'Choisissez un sujet...' => '',
                    '💼 Demande de devis' => 'devis',
                    '🌐 Site vitrine' => 'site-vitrine',
                    '🛒 Site e-commerce' => 'e-commerce',
                    '📅 Système de prise de RDV' => 'rdv',
                    '⚙️ Application sur-mesure' => 'application',
                    '🔧 Maintenance / Support' => 'maintenance',
                    '📞 Prise de contact' => 'contact',
                    '❓ Autre demande' => 'autre',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un sujet'),
                ],
                'attr' => [
                    'class' => 'form-select',
                    'data-contact-target' => 'field',
                    'style' => 'color-scheme: dark;',
                ],
            ])

            ->add('message', TextareaType::class, [
                'label' => 'Votre message',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le message est obligatoire'),
                    new Assert\Length(
                        min: 10,
                        max: 2000,
                        minMessage: 'Votre message doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Votre message ne peut pas dépasser {{ limit }} caractères'
                    ),
                ],
                'attr' => [
                    'class' => 'form-textarea',
                    'placeholder' => 'Décrivez votre projet, vos besoins, vos contraintes...',
                    'rows' => 6,
                    'data-contact-target' => 'field',
                ],
            ])
            ->add('envoyer', SubmitType::class, [
                'label' => 'Envoyer ma demande',
                'attr' => [
                    'class' => 'btn-primary w-full group',
                    'data-contact-target' => 'submit',
                    'data-action' => 'click->contact#handleSubmit',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Pas d'entité, on travaille avec un array
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'contact_form',
        ]);
    }
}
