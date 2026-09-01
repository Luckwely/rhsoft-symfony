<?php

namespace App\Form;

use App\Entity\AvanceSalaire;
use App\Entity\User;
use App\Service\AvanceLimitService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AvanceSalaireType extends AbstractType
{
    public function __construct(private AvanceLimitService $avanceLimitService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $employee */
        $employee = $options['employee'];
        $minimum = $this->avanceLimitService->getMontantMinimum();

        $disponible = $employee ? $this->avanceLimitService->getMontantDisponible($employee) : 0.0;

        $builder
            ->add('montant', NumberType::class, [
                'html5' => true,
                'scale' => 2,
                'constraints' => [
                    new Callback(function (mixed $value, ExecutionContextInterface $context) use ($employee, $minimum, $disponible): void {
                        if ($value === null || $value === '') {
                            return;
                        }

                        if (!is_numeric($value)) {
                            $context->buildViolation('Veuillez saisir un montant numérique valide.')
                                ->addViolation();
                            return;
                        }

                        $montant = (float) $value;
                        if ($montant < $minimum) {
                            $context->buildViolation(sprintf(
                                'Le montant minimum d\'une avance est de %.2f.',
                                $minimum
                            ))->addViolation();
                            return;
                        }

                        if ($employee !== null && $montant > $disponible + 0.01) {
                            $context->buildViolation($disponible > 0
                                ? sprintf(
                                    'Le montant demandé dépasse le plafond autorisé pour votre salaire. '
                                    .'Montant maximum disponible actuellement : %.2f (compte tenu de vos avances en cours non encore remboursées).',
                                    $disponible
                                )
                                : 'Vous avez atteint le plafond d\'avance autorisé pour votre salaire. '
                                    .'Aucune nouvelle demande n\'est possible tant que vos avances en cours n\'ont pas été remboursées.')
                                ->addViolation();
                        }
                    }),
                ],
                'help' => $employee !== null
                    ? sprintf(
                        'Montant disponible : %.2f (plafond fixé à %d%% du salaire de base, avances en cours déduites).',
                        $disponible,
                        $this->avanceLimitService->getPlafondPourcentage($employee)
                    )
                    : null,
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
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner un motif'),
                ],
            ])
            ->add('commentaire', TextareaType::class, [
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 500,
                        maxMessage: 'Le commentaire ne doit pas dépasser {{ limit }} caractères'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AvanceSalaire::class,
            'employee' => null,
        ]);
        $resolver->setAllowedTypes('employee', ['null', User::class]);
    }
}
