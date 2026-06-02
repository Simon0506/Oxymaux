<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', null, [
                'label' => 'Prénom',
                'required' => true
            ])
            ->add('lastname', null, [
                'label' => 'Nom',
                'required' => true
            ])
            ->add('phone', null, [
                'label' => 'Téléphone',
                'required' => false
            ])
            ->add('address', null, [
                'label' => 'Adresse',
                'required' => false
            ])
            ->add('postalCode', null, [
                'label' => 'Code postal',
                'required' => false
            ])
            ->add('city', null, [
                'label' => 'Ville',
                'required' => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
