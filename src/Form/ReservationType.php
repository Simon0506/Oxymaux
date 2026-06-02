<?php

namespace App\Form;

use App\Entity\Dog;
use App\Entity\Reservation;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fullNameCallback = function (Dog $dog) {
            return $dog->getFullName() . ' - Propriétaire : ' . $dog->getUser()->getFirstName() . ' ' . $dog->getUser()->getLastName();
        };
        $builder
            ->add('name')
            ->add('dog', EntityType::class, [
                'class' => Dog::class,
                'choice_label' => $fullNameCallback,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('d')
                        ->leftJoin('d.user', 'u')
                        ->addSelect('u')
                        ->orderBy('d.name', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
