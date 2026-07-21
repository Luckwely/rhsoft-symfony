<?php
namespace App\Form;

use App\Entity\Planning;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanningType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dayOfWeek', null, ['disabled' => true, 'label' => 'Jour'])
            ->add('isDayOff', CheckboxType::class, ['required' => false, 'label' => 'Repos'])
            ->add('heureDebut', TimeType::class, ['widget' => 'single_text', 'required' => false])
            ->add('heureFin', TimeType::class, ['widget' => 'single_text', 'required' => false])
            ->add('pause', TimeType::class, ['widget' => 'single_text', 'required' => false])
            ->add('pausette', TimeType::class, ['widget' => 'single_text', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Planning::class]);
    }
}
