<?php

namespace App\Form\Admin;

use App\Entity\Conge;
use App\Entity\Entreprise;
use App\Entity\TypeConge;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;


class CongeAdminType extends AbstractType
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
                        ->andWhere('u.roles NOT LIKE :roleAdmin')
                        ->setParameter('entreprise', $entreprise)
                        ->setParameter('roleAdmin', '%ROLE_ADMIN%')
                        ->orderBy('u.nom', 'ASC');
                },
            ])
            ->add('typeConge', EntityType::class, [
                'class' => TypeConge::class,
                'choice_label' => 'nom',
                'label' => 'Type de congé',
                'placeholder' => 'Sélectionner un type',
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner un type de congé'),
                ],
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de début',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'La date de début est obligatoire'),
                ],
            ])
            ->add('dateFin', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'label' => 'Date de fin',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(message: 'La date de fin est obligatoire'),
                ],
            ])
            ->add('motif', TextareaType::class, [
                'required' => false,
                'label' => 'Motif / Commentaire',
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => "Précisez le motif de la demande..."],
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
        $resolver->setRequired('entreprise');
        $resolver->setAllowedTypes('entreprise', Entreprise::class);
    }
}
