<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CompanySettings;
use App\Entity\PDPMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;

class CompanySettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ===== INFORMATIONS ENTREPRISE =====
            ->add('raisonSociale', TextType::class, [
                'label' => 'Raison sociale',
                'required' => true,
                'attr' => ['class' => 'form-input']
            ])
            ->add('siren', TextType::class, [
                'label' => 'SIREN',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 9, 'pattern' => '[0-9]{9}']
            ])
            ->add('siret', TextType::class, [
                'label' => 'SIRET',
                'required' => false,
                'attr' => ['class' => 'form-input', 'maxlength' => 14, 'pattern' => '[0-9]{14}']
            ])
            ->add('adresse', TextareaType::class, [
                'label' => 'Adresse',
                'required' => true,
                'attr' => ['class' => 'form-textarea', 'rows' => 3]
            ])
            ->add('codePostal', TextType::class, [
                'label' => 'Code postal',
                'required' => true,
                'attr' => ['class' => 'form-input']
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => true,
                'attr' => ['class' => 'form-input']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email de contact',
                'required' => false,
                'attr' => ['class' => 'form-input'],
                'help' => 'Si vide, l\'email de votre compte sera utilisé pour les devis et factures',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-input']
            ])
            ->add('logo', FileType::class, [
                'label' => 'Logo de l\'entreprise',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-input', 'accept' => 'image/*'],
                'constraints' => [
                    new Image([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/svg+xml'],
                        'mimeTypesMessage' => 'Le logo doit être une image (JPEG, PNG ou SVG)',
                    ])
                ],
                'help' => 'Format accepté : JPEG, PNG ou SVG (max 2Mo). Le logo apparaîtra sur vos devis et factures.',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])

            // ===== CONFIGURATION TVA =====
            ->add('tvaEnabled', CheckboxType::class, [
                'label' => 'TVA activée',
                'required' => false,
                'attr' => ['class' => 'form-checkbox']
            ])
            ->add('tauxTVADefaut', ChoiceType::class, [
                'label' => 'Taux de TVA par défaut',
                'required' => false,
                'choices' => [
                    '0 %' => '0.00',
                    '5,5 %' => '5.50',
                    '10 %' => '10.00',
                    '20 %' => '20.00',
                ],
                'placeholder' => 'Sélectionner un taux',
                'attr' => ['class' => 'form-select']
            ])

            // ===== CONFIGURATION PDP =====
            ->add('pdpMode', ChoiceType::class, [
                'label' => 'Mode de facturation électronique (PDP)',
                'choices' => [
                    'Aucun' => PDPMode::NONE->value,
                    'Sandbox (Test)' => PDPMode::SANDBOX->value,
                    'Production' => PDPMode::PRODUCTION->value,
                ],
                'required' => true,
                'attr' => ['class' => 'form-select']
            ])
            ->add('pdpProvider', TextType::class, [
                'label' => 'Provider PDP',
                'required' => false,
                'attr' => ['class' => 'form-input'],
                'help' => 'Ex: Jefacture, DPii, Pennylane...',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('pdpApiKey', TextType::class, [
                'label' => 'Clé API PDP',
                'required' => false,
                'attr' => ['class' => 'form-input', 'type' => 'password'],
                'help' => 'Clé API pour l\'intégration avec la plateforme PDP',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])

            // ===== CONFIGURATION SIGNATURE =====
            ->add('signatureProvider', ChoiceType::class, [
                'label' => 'Provider de signature électronique',
                'choices' => [
                    'Service personnalisé' => 'custom',
                    'YouSign' => 'yousign',
                    'DocuSign' => 'docusign',
                ],
                'required' => false,
                'placeholder' => 'Aucun',
                'attr' => ['class' => 'form-select']
            ])
            ->add('signatureApiKey', TextType::class, [
                'label' => 'Clé API Signature',
                'required' => false,
                'attr' => ['class' => 'form-input', 'type' => 'password']
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer les paramètres',
                'attr' => ['class' => 'btn btn-primary']
            ]);

        // Sécurité validation: si TVA décochée à la soumission, retirer tauxTVADefaut
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (!$data) {
                return;
            }

            $tvaEnabled = array_key_exists('tvaEnabled', $data) ? (bool) $data['tvaEnabled'] : false;
            if (!$tvaEnabled) {
                // Option: forcer à 0.00 côté données
                if (isset($data['tauxTVADefaut'])) {
                    $data['tauxTVADefaut'] = '0.00';
                    $event->setData($data);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompanySettings::class,
        ]);
    }
}
