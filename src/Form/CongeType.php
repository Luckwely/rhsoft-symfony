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
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CongeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
                    'min' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                ],
                'constraints' => [
                    new GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date de début ne peut pas être antérieure à aujourd\'hui'
                    ),
                ],
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de fin',
                'attr' => [
                    'class' => 'form-control',
                    'min' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                ],
                'constraints' => [
                    new GreaterThanOrEqual(
                        value: 'today',
                        message: 'La date de fin ne peut pas être antérieure à aujourd\'hui'
                    ),
                ],
            ])
            ->add('motif', TextareaType::class, [
                'required' => false,
                'label' => 'Motif / Commentaire',
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'constraints' => [
                    new Length(
                        max: 500,
                        maxMessage: 'Le motif ne doit pas dépasser {{ limit }} caractères'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Conge::class,
            'constraints' => [
                new Callback(function (?Conge $conge, ExecutionContextInterface $context): void {
                    if ($conge === null || $conge->getDateDebut() === null || $conge->getDateFin() === null) {
                        return;
                    }

                    if ($conge->getDateFin() < $conge->getDateDebut()) {
                        $context->buildViolation('La date de fin doit être postérieure ou égale à la date de début.')
                            ->atPath('dateFin')
                            ->addViolation();
                    }
                }),
            ],
        ]);
    }
}
