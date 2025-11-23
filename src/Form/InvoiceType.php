<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\Client;
use App\Entity\Quote;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;
use App\Entity\CompanySettings;
use App\EventSubscriber\RemoveEmptyLinesSubscriber;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $invoice = $options['data'] ?? null;
        $hasQuote = $invoice && $invoice->getQuote() !== null;
        
        // Vérifier si chaque champ a une valeur pré-remplie depuis le devis
        $hasMontantAcompteFromQuote = $hasQuote && $invoice->getMontantAcompte() !== null && $invoice->getMontantAcompte() > 0;
        $hasConditionsPaiementFromQuote = $hasQuote && $invoice->getConditionsPaiement() !== null && trim($invoice->getConditionsPaiement()) !== '';
        $hasDelaiPaiementFromQuote = $hasQuote && $invoice->getDelaiPaiement() !== null;
        $isEdit = $invoice && $invoice->getId();
        $canEdit = !$isEdit || ($invoice && $invoice->canBeModified());
        
        // Si un devis est associé, les lignes ne peuvent pas être modifiées (légalité)
        $linesEditable = $canEdit && !$hasQuote;

        $builder
            ->add('numero', TextType::class, [
                'label' => 'Numéro de la facture',
                'required' => false,
                'disabled' => true,
                'attr' => [
                    'class' => 'form-input',
                    'readonly' => true
                ],
                'help' => 'Le numéro sera généré automatiquement lors de la création (format: FACT-YYYY-XXX)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('quote', EntityType::class, [
                'label' => 'Devis associé',
                'class' => Quote::class,
                'choice_label' => function (Quote $quote) {
                    return sprintf('%s - %s (%s)', 
                        $quote->getNumero() ?? 'Devis #' . $quote->getId(),
                        $quote->getClient() ? $quote->getClient()->getNomComplet() : 'Client inconnu',
                        $quote->getMontantTTCFormate()
                    );
                },
                'required' => false,
                'placeholder' => 'Rechercher un devis...',
                'query_builder' => function (EntityRepository $er) use ($options) {
                    $qb = $er->createQueryBuilder('q')
                        ->leftJoin('q.invoice', 'i')
                        ->where('q.statut = :signed')
                        ->setParameter('signed', \App\Entity\QuoteStatus::SIGNED);
                    
                    // Si on édite une facture existante, permettre de voir le devis associé
                    $invoice = $options['data'] ?? null;
                    if ($invoice && $invoice->getId() && $invoice->getQuote()) {
                        $qb->andWhere('i.id IS NULL OR i.id = :currentInvoiceId')
                           ->setParameter('currentInvoiceId', $invoice->getId());
                    } else {
                        // En création, exclure les devis qui ont déjà une facture
                        $qb->andWhere('i.id IS NULL');
                    }
                    
                    return $qb->orderBy('q.dateCreation', 'DESC');
                },
                'attr' => ['class' => 'form-select'],
                'disabled' => $hasQuote, // Désactiver si un devis est déjà associé (mais créer un champ caché pour la soumission)
                'help' => 'Sélectionnez un devis signé pour créer la facture automatiquement (seuls les devis sans facture sont affichés)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('client', EntityType::class, [
                'label' => 'Client',
                'class' => Client::class,
                'choice_label' => 'nomComplet',
                'required' => true,
                'placeholder' => 'Rechercher un client...',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.nom', 'ASC');
                },
                'attr' => ['class' => 'form-select', 'required' => 'required'],
                'help' => 'Tapez pour rechercher un client dans la liste',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('statut', EnumType::class, [
                'label' => 'Statut',
                'class' => InvoiceStatus::class,
                'choice_label' => fn(InvoiceStatus $status) => $status->getLabel(),
                'property_path' => 'statutEnum',
                'attr' => ['class' => 'form-select'],
                'help' => 'Statut de la facture',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('dateEcheance', DateType::class, [
                'label' => 'Date d\'échéance',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input', 'required' => 'required'],
                'help' => 'Date d\'échéance de paiement (par défaut : 30 jours)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('montantAcompte', NumberType::class, [
                'label' => 'Montant d\'acompte (€)',
                'scale' => 2,
                'required' => false,
                // Ne pas utiliser 'disabled' car cela empêche la soumission du formulaire
                // Utiliser seulement 'readonly' dans les attributs pour que la valeur soit soumise
                // Rendre readonly seulement si une valeur a été pré-remplie depuis le devis
                'attr' => [
                    'class' => 'form-input',
                    'step' => '0.01',
                    'min' => '0',
                    'readonly' => $hasMontantAcompteFromQuote ? 'readonly' : false
                ],
                'help' => $hasMontantAcompteFromQuote ? 'Montant d\'acompte du devis associé (lecture seule)' : 'Montant d\'acompte déjà reçu',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('conditionsPaiement', TextareaType::class, [
                'label' => 'Conditions de paiement',
                'required' => false,
                // Ne pas utiliser 'disabled' car cela empêche la soumission du formulaire
                // Utiliser seulement 'readonly' dans les attributs pour que la valeur soit soumise
                // Rendre readonly seulement si une valeur a été pré-remplie depuis le devis
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 3,
                    'readonly' => $hasConditionsPaiementFromQuote ? 'readonly' : false
                ],
                'help' => $hasConditionsPaiementFromQuote ? 'Conditions de paiement du devis associé (lecture seule)' : 'Conditions de paiement (ex: 30 jours, à réception, etc.)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('delaiPaiement', NumberType::class, [
                'label' => 'Délai de paiement (jours)',
                'required' => false,
                // Ne pas utiliser 'disabled' car cela empêche la soumission du formulaire
                // Utiliser seulement 'readonly' dans les attributs pour que la valeur soit soumise
                // Rendre readonly seulement si une valeur a été pré-remplie depuis le devis
                'attr' => [
                    'class' => 'form-input',
                    'min' => '0',
                    'readonly' => $hasDelaiPaiementFromQuote ? 'readonly' : false
                ],
                'help' => $hasDelaiPaiementFromQuote ? 'Délai de paiement du devis associé (lecture seule)' : 'Délai de paiement en jours',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('penalitesRetard', NumberType::class, [
                'label' => 'Pénalités de retard (%)',
                'scale' => 2,
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100'
                ],
                'help' => 'Taux de pénalités de retard par jour',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->addEventSubscriber(new RemoveEmptyLinesSubscriber())
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'rows' => 4
                ],
                'help' => 'Notes internes (non visibles sur la facture)',
                'help_attr' => ['class' => 'text-white/90 text-sm mt-1']
            ])
            ->add('lines', CollectionType::class, [
                'entry_type' => InvoiceLineType::class,
                'entry_options' => ['label' => false],
                'allow_add' => $linesEditable,
                'allow_delete' => $linesEditable,
                'by_reference' => false,
                'label' => false,
                // Ne pas utiliser 'disabled' sur la collection car cela empêche la soumission
                // Les champs individuels seront en readonly via le template Twig
                'attr' => ['class' => 'invoice-lines-collection']
            ]);

        // Ajouter le subscriber pour gérer les mises à jour liées au devis
        $builder->addEventSubscriber(new \App\Form\EventSubscriber\InvoiceQuoteSubscriber($this->entityManager));

        // Ajouter un listener pour mettre à jour le query_builder du champ quote et ajouter les lignes manquantes
        // Ce listener reste nécessaire pour la reconstruction du formulaire (query_builder) et l'ajout des champs de lignes
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            // Récupérer l'ID du devis depuis les données soumises
            $quoteId = null;
            if (isset($data['quote'])) {
                $quoteValue = $data['quote'];
                
                if (is_numeric($quoteValue)) {
                    $quoteId = (int)$quoteValue;
                } elseif (is_string($quoteValue) && is_numeric($quoteValue)) {
                    $quoteId = (int)$quoteValue;
                }
            }

            // Si on a un ID de devis, mettre à jour le query_builder du champ quote
            if ($quoteId && $form->has('quote')) {
                $quoteField = $form->get('quote');
                $quoteOptions = $quoteField->getConfig()->getOptions();
                
                // Mettre à jour le query_builder pour inclure le devis sélectionné
                $quoteOptions['query_builder'] = function (EntityRepository $er) use ($quoteId) {
                    $qb = $er->createQueryBuilder('q')
                        ->leftJoin('q.invoice', 'i')
                        ->where('(q.statut = :signed AND i.id IS NULL) OR q.id = :quoteId')
                        ->setParameter('signed', \App\Entity\QuoteStatus::SIGNED)
                        ->setParameter('quoteId', $quoteId);
                    
                    return $qb->orderBy('q.dateCreation', 'DESC');
                };
                
                // IMPORTANT: Ne pas désactiver le champ lors de la reconstruction
                $quoteOptions['disabled'] = false;
                
                // Reconstruire le champ quote avec le nouveau query_builder
                $form->remove('quote');
                $form->add('quote', EntityType::class, $quoteOptions);
            }

            // Si on a un ID de devis, ajouter les lignes manquantes au formulaire
            $linesData = $data['lines'] ?? [];
            
            if ($quoteId && $form->has('lines')) {
                $linesForm = $form->get('lines');
                
                // Si des lignes sont dans les données mais pas dans le formulaire, les ajouter
                if (count($linesData) > count($linesForm->all())) {
                    for ($i = count($linesForm->all()); $i < count($linesData); $i++) {
                        $linesForm->add((string)$i, InvoiceLineType::class, [
                            'label' => false,
                        ]);
                    }
                }
            }
        });
    }

    public function __construct(
        private \Doctrine\ORM\EntityManagerInterface $entityManager
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
            'company_settings' => null,
        ]);
        $resolver->setAllowedTypes('company_settings', ['null', CompanySettings::class]);
    }
}

