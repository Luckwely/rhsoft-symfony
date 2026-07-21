<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\{
    FileType,
    TextType,
    DateType,
    NumberType,
    TextareaType,
};
use Symfony\Component\Validator\Constraints\{
    NotBlank,
    Email,
    File
};


class EmployeeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'constraints' => [
                    new NotBlank(
                        message: "Le prénom est obligatoire"
                    )
                ]
            ])
            ->add('nom', TextType::class, [
                'constraints' => [
                    new NotBlank(
                        message: 'Le nom est obligatoire'
                    )
                ]
            ])
            ->add('email', TextType::class, [
                'constraints' => [
                    new NotBlank(
                        message: 'Email obligatoire'
                    ),
                    new Email(
                        message: 'Email invalide'
                    )
                ]
            ])
            ->add('telephone', TextType::class, ['required' => false])
            ->add('adresse', TextType::class, ['required' => false]) // mieux pour adresse
            ->add('photo', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Merci d\'uploader une image JPG ou PNG',
                    )
                ]
            ])

            ->add('dateEmbauche', DateType::class, [
                'widget' => 'single_text',
                'required' => false
            ])

            ->add('soldeConge', NumberType::class, [
                'required' => false
            ])

            ->add('cv', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes:  ['application/pdf'],
                        mimeTypesMessage:  'Merci d\'uploader un PDF',
                    )
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
