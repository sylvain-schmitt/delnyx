<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CompanySettings;
use App\Entity\PDPMode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Form\CallbackTransformer;

class CompanySettingsType extends AbstractType
{
    public function __construct(
        private Security $security
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

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
                'attr' => ['class' => 'form-input', 'accept' => 'image/jpeg, image/png'],
                'constraints' => [
                    new Image([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png'],
                        'mimeTypesMessage' => 'Le logo doit être une image (JPEG ou PNG)',
                    ])
                ],
                'help' => 'Format accepté : JPEG ou PNG (max 2Mo). Le logo apparaîtra sur vos devis et factures.',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])

            // ===== MENTIONS LÉGALES OBLIGATOIRES =====
            ->add('formeJuridique', ChoiceType::class, [
                'label' => 'Forme juridique',
                'required' => true,
                'choices' => [
                    'Micro-entrepreneur' => 'Micro-entrepreneur',
                    'Entreprise individuelle (EI)' => 'Entreprise individuelle',
                    'EURL' => 'EURL',
                    'SASU' => 'SASU',
                    'SARL' => 'SARL',
                    'SAS' => 'SAS',
                ],
                'attr' => ['class' => 'form-select'],
                'help' => 'Apparaît sur vos documents officiels',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('codeAPE', TextType::class, [
                'label' => 'Code APE / NAF',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: 6201Z'],
                'help' => 'Code d\'activité principale (5 caractères)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('assuranceRCPro', TextareaType::class, [
                'label' => 'Assurance RC Professionnelle',
                'required' => false,
                'attr' => ['class' => 'form-textarea', 'rows' => 2, 'placeholder' => 'Nom de l\'assureur, numéro de contrat...'],
                'help' => 'Obligatoire pour certaines professions réglementées',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('indemniteForfaitaireRecouvrement', NumberType::class, [
                'label' => 'Indemnité forfaitaire de recouvrement (€)',
                'required' => true,
                'scale' => 2,
                'attr' => ['class' => 'form-input', 'min' => 40],
                'help' => 'Minimum légal : 40 € (art. D.441-5 du Code de commerce)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])

            // ===== CONFIGURATION BANCAIRE =====
            ->add('iban', TextType::class, [
                'label' => 'IBAN',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'FR76...'],
                'help' => 'Affiché sur les factures pour les paiements par virement',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('bic', TextType::class, [
                'label' => 'BIC / SWIFT',
                'required' => false,
                'attr' => ['class' => 'form-input'],
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
            ->add('pdpApiKey', PasswordType::class, [
                'label' => 'Clé API PDP',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-input', 'autocomplete' => 'new-password'],
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
            ->add('signatureApiKey', PasswordType::class, [
                'label' => 'Clé API Signature',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-input', 'autocomplete' => 'new-password']
            ])

            // ===== CONFIGURATION STRIPE (Paiement en ligne) =====
            ->add('stripeEnabled', CheckboxType::class, [
                'label' => 'Activer les paiements Stripe',
                'required' => false,
                'attr' => ['class' => 'form-checkbox'],
                'help' => 'Active le paiement par carte bancaire pour les factures et acomptes',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('stripePublishableKey', TextType::class, [
                'label' => 'Clé publique Stripe',
                'required' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'pk_test_... ou pk_live_...'],
                'help' => 'Utilisée pour le frontend (Stripe Elements)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('stripeSecretKey', PasswordType::class, [
                'label' => 'Clé secrète Stripe',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'sk_test_... ou sk_live_...', 'autocomplete' => 'new-password'],
                'help' => 'Ne jamais exposer côté client - utilisée uniquement côté serveur',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('stripeWebhookSecret', PasswordType::class, [
                'label' => 'Secret webhook Stripe',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-input', 'placeholder' => 'whsec_...', 'autocomplete' => 'new-password'],
                'help' => 'Récupérez-le depuis le dashboard Stripe > Webhooks > Détails du endpoint',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ]);

        // ===== CONFIGURATION GOOGLE (Reviews & Calendar) - RESTREINT ADMIN =====
        if ($isAdmin) {
            $builder
                // Avis Google
                ->add('googleReviewsEnabled', CheckboxType::class, [
                    'label' => 'Activer les avis Google Business',
                    'required' => false,
                    'attr' => ['class' => 'form-checkbox'],
                    'help' => 'Affiché sur la page d\'accueil pour la preuve sociale',
                    'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
                ])
                ->add('googlePlaceId', TextType::class, [
                    'label' => 'Google Place ID',
                    'required' => false,
                    'attr' => ['class' => 'form-input', 'placeholder' => 'ChIJs...'],
                    'help' => 'Identifiant unique de votre établissement sur Google Maps',
                    'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
                ])
                ->add('googleApiKey', PasswordType::class, [
                    'label' => 'Clé API Google Places',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-input', 'placeholder' => 'AIzaSy...', 'autocomplete' => 'new-password'],
                    'help' => 'Nécessite une clé avec l\'API "Places API" activée dans Google Cloud Console',
                    'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
                ])
                // Google Calendar
                ->add('googleCalendarEnabled', CheckboxType::class, [
                    'label' => 'Activer Google Calendar',
                    'required' => false,
                    'attr' => ['class' => 'form-checkbox'],
                    'help' => 'Permet la synchronisation des rendez-vous et des créneaux',
                    'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
                ])
                ->add('googleClientId', TextType::class, [
                    'label' => 'Client ID Google',
                    'required' => false,
                    'attr' => ['class' => 'form-input', 'placeholder' => 'xxxx.apps.googleusercontent.com'],
                    'help' => 'À récupérer dans la console Google Cloud > Identifiants',
                    'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
                ])
                ->add('googleClientSecret', PasswordType::class, [
                    'label' => 'Client Secret Google',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-input', 'autocomplete' => 'new-password'],
                    'help' => 'À récupérer dans la console Google Cloud > Identifiants',
                    'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
                ])
                ->add('googleCalendarId', TextType::class, [
                    'label' => 'ID Agenda Google',
                    'required' => false,
                    'attr' => ['class' => 'form-input', 'placeholder' => 'primary ou email@gmail.com'],
                    'help' => '"primary" utilisera votre agenda par défaut',
                    'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
                ])
                ->add('workingHours', TextareaType::class, [
                    'label' => 'Plages horaires disponibles (JSON)',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-textarea font-mono text-xs',
                        'rows' => 8,
                        'placeholder' => '{
  "monday": ["18:00-20:00"],
  "tuesday": ["18:00-20:00"],
  "wednesday": ["18:00-20:00"],
  "thursday": ["18:00-20:00"],
  "friday": ["14:00-18:00"]
}'
                    ],
                    'help' => 'Format JSON. Exemple : {"monday": ["18:00-20:00"]}. Laissez vide pour ne pas avoir de disponibilités.',
                    'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
                ]);

            $builder->get('workingHours')->addModelTransformer(new CallbackTransformer(
                function ($hoursAsArray) {
                    // array to string (for textarea)
                    return $hoursAsArray ? json_encode($hoursAsArray, JSON_PRETTY_PRINT) : '';
                },
                function ($hoursAsString) {
                    // string to array (for entity)
                    if (!$hoursAsString) return null;
                    return json_decode($hoursAsString, true);
                }
            ));
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'Enregistrer les paramètres',
            'attr' => ['class' => 'btn btn-primary']
        ]);

        // Logic TVA
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (!$data) return;

            $tvaEnabled = array_key_exists('tvaEnabled', $data) ? (bool) $data['tvaEnabled'] : false;

            if (isset($data['iban'])) {
                $data['iban'] = mb_strtoupper(str_replace(' ', '', $data['iban']));
            }

            if (!$tvaEnabled) {
                $data['tauxTVADefaut'] = '0.00';
            } elseif (empty($data['tauxTVADefaut'])) {
                $data['tauxTVADefaut'] = '20.00';
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CompanySettings::class,
        ]);
    }
}
