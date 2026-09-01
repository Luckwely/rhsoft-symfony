<?php

namespace App\Form;

use App\Entity\Demission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateDepart', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'min' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La date de départ est obligatoire.'),
                    new Assert\GreaterThan('today', message: 'La date de départ doit être ultérieure à aujourd’hui.'),
                ],
            ])
            ->add('motif', TextareaType::class, [
                'attr' => ['rows' => 4, 'placeholder' => 'Précisez les motifs de votre démission...'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez préciser le motif de votre démission.'),
                    new Assert\Length(
                        min: 10,
                        max: 1000,
                        minMessage: 'Le motif doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Le motif ne doit pas dépasser {{ limit }} caractères'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Demission::class,
        ]);
    }
}
