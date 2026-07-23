<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{
    RepeatedType,
    PasswordType,
    TextType,
    CheckboxType,
    EmailType,
    FileType
};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Votre nom',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le nom',
                        ),
                    new length (
                        min: 2,
                        max: 50,
                        minMessage: 'Le nom doit être au moins 2 caractère',
                        maxMessage: 'Le nom ne doit pas dépasser {{limit}} caractere'
                    )
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Votre prénom',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le prénom',
                        ),
                    new length (
                        min: 2,
                        max: 50,
                        minMessage: 'Le prenom doit être au moins 2 caractère',
                        maxMessage: 'Le prenom ne doit pas dépasser {{limit}} caractere'
                    )
                ]
            ])
            ->add('nom_entreprise', TextType::class, [
                'mapped' => false,
                'label' => 'Nom de votre entreprise',
                'attr' => [
                    'placeholder' => 'Nom de votre entreprise',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le nom de votre entreprise',
                        ),
                    new length (
                        min: 2,
                        max: 50,
                        minMessage: 'Le nom doit être au moins 2 caractère',
                        maxMessage: 'Le nom ne doit pas dépasser {{limit}} caractere'
                    )
                ]
            ])

            ->add('nif_entreprise', TextType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'NIF',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '123456789'
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le NIF',
                        ),
                ]
            ])

            ->add('adresse_entreprise', TextType::class, [
                'mapped' => false,
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => "Adresse complète de l'entreprise",
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer l\'adresse',
                        ),
                ]
            ])

            ->add('tel_entreprise', TextType::class, [
                'mapped' => false,
                'label' => 'Téléphone',
                'attr' => [
                    'placeholder' => '+261 XX XX XXX XX',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le telephone',
                        ),
                ]
            ])

            ->add('logo_entreprise', FileType::class, [
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => "Logo de l'entreprise",
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le logo de votre entreprise',
                        ),
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Veuillez télécharger une image JPG, PNG ou WEBP valide',
                    )
                ]

            ])

            ->add('email', EmailType::class, [
                'label' => 'Votre email',
                'attr' => [
                    'placeholder' => 'email@gmail.com',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank (
                        message: 'Veuillez entrer l\'email de votre entreprise'
                    ),
                    new Email (
                        message: "L'email {{value}} n'est pas un email valid"
                    )
                ]
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Acceptez nos terme & conditions',
                'constraints' => [
                    new IsTrue(
                        message: 'Vous pouvez accepter notre Terme',
                    ),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'label' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'class' => 'form-control p-0 m-0'
                ],
                'invalid_message' => "le mot de passe ne correspond pas",
                'required' => true,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => [
                        'placeholder' => 'Votre mot de passe',
                        'class' => 'form-control'
                    ],
                    'hash_property_path' => 'password',
                    'constraints' => [
                        new NotBlank(
                            message: 'Veuillez entrer le mot de passe',
                        ),
                        new Length(
                            min: 6,
                            minMessage: 'Votre mot de passe ne doit pas dépasser {{ limit }} caractères',
                            // max length allowed by Symfony for security reasons
                            max: 4096,
                        )
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirmation mot de passe',
                    'attr' => [
                        'placeholder' => 'Confirmez votre mot de passe',
                        'class' => 'form-control'
                    ],
                    'constraints' => [
                        new NotBlank(
                            message: 'Veuillez confirmer le mot de passe',
                        ),
                    ]
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
