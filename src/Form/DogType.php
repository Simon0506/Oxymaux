<?php

namespace App\Form;

use App\Entity\Dog;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('race')
            ->add('sexe', ChoiceType::class, [
                'choices' => [
                    'Sélectionner' => '',
                    'Male' => 'Male',
                    'Femelle' => 'Femelle',
                ],
            ])
            ->add('photo', FileType::class, [
                'label' => 'Photo du chien',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    // Correction ici : on utilise les arguments nommés de PHP 8
                    new File(
                        maxSize: '5M',
                        mimeTypes: [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/webp',
                        ],
                        mimeTypesMessage: 'Veuillez uploader une image valide'
                    )
                ],
            ])
            ->add('dateOfBirth')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dog::class,
        ]);
    }
}
