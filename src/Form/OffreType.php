<?php
namespace App\Form;

use App\Entity\Offre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class OffreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'attr' => ['maxlength' => 100],
                'constraints' => [
                    new NotBlank(message: 'Le titre est obligatoire'),
                    new Length(
                        min: 3,
                        max: 100,
                        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères'
                    ),
                ],
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['rows' => 8],
                'constraints' => [
                    new NotBlank(message: 'La description est obligatoire'),
                    new Length(
                        min: 10,
                        max: 5000,
                        minMessage: 'La description doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'La description ne doit pas dépasser {{ limit }} caractères'
                    ),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Ouverte' => 'ouverte',
                    'Fermée' => 'fermee',
                    'Brouillon' => 'brouillon',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez sélectionner un statut'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offre::class,
        ]);
    }
}
