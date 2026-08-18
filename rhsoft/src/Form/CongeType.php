<?php

namespace App\Form;

use App\Entity\Conge;
use App\Entity\TypeConge;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CongeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = (new \DateTime())->format('Y-m-d');

        $builder
            ->add('typeConge', EntityType::class, [
                'class' => TypeConge::class,
                'choice_label' => 'nom',
                'label' => 'Type de congé',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de début',
                'attr' => [
                    'class' => 'form-control',
                    'min' => $today, 
                ],
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de fin',
                'attr' => [
                    'class' => 'form-control',
                    'min' => $today,
                ],
            ])
            ->add('motif', TextareaType::class, [
                'required' => false,
                'label' => 'Motif / Commentaire',
                'attr' => ['class' => 'form-control', 'rows' => 3],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Conge::class,
        ]);
    }
}