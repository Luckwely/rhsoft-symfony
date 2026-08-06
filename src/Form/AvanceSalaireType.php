<?php

namespace App\Form;

use App\Entity\AvanceSalaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class AvanceSalaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', MoneyType::class, [
                'currency' => false,
                'constraints' => [
                    new GreaterThanOrEqual(50),
                    new LessThanOrEqual(800),
                ],
            ])
            ->add('motif', ChoiceType::class, [
                'choices' => [
                    'Dépense urgente' => 'urgent',
                    'Frais médicaux' => 'medical',
                    'Frais de scolarité' => 'education',
                    'Logement / Loyer' => 'logement',
                    'Transport / Véhicule' => 'transport',
                    'Autre' => 'autre',
                ],
                'placeholder' => 'Sélectionner un motif',
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AvanceSalaire::class,
        ]);
    }
}
