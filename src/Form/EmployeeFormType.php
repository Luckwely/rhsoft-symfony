<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\{
    FileType,
    TextType,
    DateType,
    NumberType,
    ChoiceType,
    EmailType
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
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Votre prénom',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(
                        message: "Le prénom est obligatoire"
                    )
                ]
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Votre nom',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Le nom est obligatoire'
                    )
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'Votre email',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Email obligatoire'
                    ),
                    new Email(
                        message: 'Email invalide'
                    )
                ]
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Telephone',
                'attr' => [
                    'placeholder' => 'Votre numero de telephone',
                    'class' => 'form-control'
                ],
                'required' => false
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => 'Votre adresse',
                    'class' => 'form-control'
                ],
                'required' => false
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôle',
                'attr' => [
                    'class' => 'form-control'
                ],
                'required' => true,
                'choices'  => [
                    'RH' => "ROLE_RH",
                    'manager' => "ROLE_MANAGER",
                    'employe' => "ROLE_EMPLOYE",
                ],
                'multiple' => false,
                'expanded' => false,
                'placeholder' => 'Choisir un rôle',
            ])
            ->add('photo', FileType::class, [
                'label' => 'Photo',
                'attr' => [
                    'placeholder' => 'Votre photo de profile',
                    'class' => 'form-control'
                ],
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
                'label' => 'Date embauche',
                'attr' => [
                    'placeholder' => 'Date',
                    'class' => 'form-control'
                ],
                'widget' => 'single_text',
                'required' => false
            ])

            ->add('soldeConge', NumberType::class, [
                'label' => 'Solde congé',
                'attr' => [
                    'placeholder' => 'Votre nombre congé',
                    'class' => 'form-control'
                ],
                'required' => false
            ])

            ->add('cv', FileType::class, [
                'label' => 'CV',
                'attr' => [
                    'placeholder' => 'Votre Cv en pdf',
                    'class' => 'form-control'
                ],
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

            $builder->get('roles')->addModelTransformer(new CallbackTransformer(
                function ($rolesArray) {
                    return $rolesArray? $rolesArray[0] : null;
                },
                function ($roleString) {
                    return $roleString? [$roleString] : ['ROLE_USER'];
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
