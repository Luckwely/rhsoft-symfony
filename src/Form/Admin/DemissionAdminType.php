<?php

namespace App\Form\Admin;

use App\Entity\Demission;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;


class DemissionAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Entreprise $entreprise */
        $entreprise = $options['entreprise'];

        $builder
            ->add('employee', EntityType::class, [
                'class' => User::class,
                'label' => 'Employé',
                'placeholder' => 'Sélectionner un employé',
                'choice_label' => fn (User $user) => trim($user->getPrenom().' '.$user->getNom()),
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner un employé'),
                ],
                'query_builder' => function (UserRepository $ur) use ($entreprise) {
                    return $ur->createQueryBuilder('u')
                        ->andWhere('u.entreprise = :entreprise')
                        ->setParameter('entreprise', $entreprise)
                        ->orderBy('u.nom', 'ASC');
                },
            ])
            ->add('dateDepart', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de départ',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'La date de départ est obligatoire'),
                ],
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif',
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Précisez le motif de la démission...'],
                'constraints' => [
                    new NotBlank(message: 'Le motif est obligatoire'),
                    new Length(
                        min: 10,
                        max: 1000,
                        minMessage: 'Le motif doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Le motif ne doit pas dépasser {{ limit }} caractères'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Demission::class,
        ]);
        $resolver->setRequired('entreprise');
        $resolver->setAllowedTypes('entreprise', Entreprise::class);
    }
}
