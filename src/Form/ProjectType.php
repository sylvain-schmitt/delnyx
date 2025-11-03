<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Technology;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Titre du projet'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-textarea',
                    'placeholder' => 'Description détaillée du projet (HTML supporté)',
                    'rows' => 8
                ]
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL du projet',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'https://exemple.com'
                ]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => Project::STATUT_BROUILLON,
                    'Publié' => Project::STATUT_PUBLIE,
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('technologies', EntityType::class, [
                'label' => 'Technologies',
                'class' => Technology::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                    'data-placeholder' => 'Sélectionnez les technologies'
                ],
                'by_reference' => false,
            ])
            ->add('images', CollectionType::class, [
                'label' => 'Images',
                'entry_type' => ProjectImageType::class,
                'entry_options' => [
                    'label' => false,
                    'is_edit' => $isEdit,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'attr' => [
                    'class' => 'project-images-collection'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'is_edit' => false,
        ]);
    }
}

