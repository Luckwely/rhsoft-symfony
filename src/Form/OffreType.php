<?php
namespace App\Form;

use App\Entity\Offre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface; // <-- AJOUTE \Form\ ICI
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class OffreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre')
            ->add('description', TextareaType::class, [
                'attr' => ['rows' => 8]
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Ouverte' => 'ouverte',
                    'Fermée' => 'fermee',
                    'Brouillon' => 'brouillon',
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Offre::class,
        ]);
    }
}
