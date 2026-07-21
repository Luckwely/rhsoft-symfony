<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class PlanningWeekType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plannings', CollectionType::class, [
                'entry_type' => PlanningType::class,
                'entry_options' => ['label' => false],
                'allow_add' => false,
                'allow_delete' => false,
                'by_reference' => false,
                'label' => false
            ])
        ;
    }
}
