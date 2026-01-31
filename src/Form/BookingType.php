<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Appointment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;

class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'mapped' => false,
                'attr' => ['placeholder' => 'Votre prénom', 'class' => 'form-input']
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'mapped' => false,
                'attr' => ['placeholder' => 'Votre nom', 'class' => 'form-input']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'mapped' => false,
                'attr' => ['placeholder' => 'votre@email.com', 'class' => 'form-input']
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => '06 00 00 00 00', 'class' => 'form-input']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Quel est votre besoin ?',
                'required' => false,
                'attr' => ['placeholder' => 'Décrivez brièvement votre projet...', 'class' => 'form-textarea', 'rows' => 3]
            ])
            ->add('startAt', HiddenType::class)
            ->add('endAt', HiddenType::class);

        $dateTransformer = new CallbackTransformer(
            function ($date) {
                // transform the object to a string
                return $date instanceof \DateTimeInterface ? $date->format('Y-m-d H:i:s') : '';
            },
            function ($dateString) {
                // transform the string back to an object
                return $dateString ? new \DateTimeImmutable($dateString) : null;
            }
        );

        $builder->get('startAt')->addModelTransformer($dateTransformer);
        $builder->get('endAt')->addModelTransformer($dateTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appointment::class,
        ]);
    }
}
