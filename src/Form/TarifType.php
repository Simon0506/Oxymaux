<?php

namespace App\Form;

use App\Entity\Tarif;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TarifType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom du tarif',
                'attr' => [
                    'placeholder' => 'Ex: "Première séance", "Séance de suivi", etc.',
                    'class' => 'ml-4 border border-sky-300 rounded px-2 py-1 w-4/5',
                ],
                'label_attr' => [
                    'class' => 'w-1/5 text-right',
                ],
            ])
            ->add('price', null, [
                'label' => 'Prix (en €)',
                'attr' => [
                    'placeholder' => 'Ex: "50", "75.99", etc.',
                    'class' => 'ml-4 border border-sky-300 rounded px-2 py-1 w-4/5',
                ],
                'label_attr' => [
                    'class' => 'w-1/5 text-right',
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de tarif',
                'label_attr' => [
                    'class' => 'w-1/5 text-right',
                ],
                'attr' => [
                    'class' => 'ml-4 border border-sky-300 rounded px-2 py-1 w-4/5',
                ],
                'choices' => [
                    '€' => '€',
                    '€/h' => '€ / h',
                    '€/demi-heure' => '€ / demi-heure',
                    '€/journée' => '€ / journée',
                    '€/demi-journée' => '€ / demi-journée',
                ],
            ])
            ->add('comment', null, [
                'label' => 'Précisions (optionnel)',
                'attr' => [
                    'placeholder' => 'Ex: "Apporter une serviette", "Prévoir une collation", etc.',
                    'class' => 'ml-4 border border-sky-300 rounded px-2 py-1 w-4/5',
                ],
                'label_attr' => [
                    'class' => 'w-1/5 text-right',
                ],
            ])
            ->add('position', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tarif::class,
        ]);
    }
}
