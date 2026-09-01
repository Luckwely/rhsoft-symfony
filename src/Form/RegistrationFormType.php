<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\Constraints\RealEmail;
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
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    // Lettres (avec accents), espaces, tirets et apostrophes uniquement
    private const REGEX_NOM = '/^[\p{L}\s\'-]+$/u';

    // Raison sociale : lettres, chiffres, espaces, ponctuation courante
    private const REGEX_ENTREPRISE = '/^[\p{L}0-9\s.,\'&-]+$/u';

    // Chiffres, espaces, +, - et parenthèses
    private const REGEX_TEL = '/^[0-9+\s().-]+$/';

    // chiffres (7 à 12 chiffres selon les cas)
    private const REGEX_NIF = '/^[0-9]{7,12}$/';

    private const REGEX_ADRESSE = '/^[\p{L}0-9\s.,\'°\/-]+$/u';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Votre nom',
                    'class' => 'form-control',
                    'maxlength' => 50,
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
                    ),
                    new Regex(
                        pattern: self::REGEX_NOM,
                        message: 'Le nom ne doit contenir que des lettres, espaces, tirets ou apostrophes'
                    ),
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Votre prénom',
                    'class' => 'form-control',
                    'maxlength' => 50,
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
                    ),
                    new Regex(
                        pattern: self::REGEX_NOM,
                        message: 'Le prénom ne doit contenir que des lettres, espaces, tirets ou apostrophes'
                    ),
                ]
            ])
            ->add('nom_entreprise', TextType::class, [
                'mapped' => false,
                'label' => 'Nom de votre entreprise',
                'attr' => [
                    'placeholder' => 'Nom de votre entreprise',
                    'class' => 'form-control',
                    'maxlength' => 100,
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le nom de votre entreprise',
                        ),
                    new length (
                        min: 2,
                        max: 100,
                        minMessage: 'Le nom doit être au moins 2 caractère',
                        maxMessage: 'Le nom ne doit pas dépasser {{limit}} caractere'
                    ),
                    new Regex(
                        pattern: self::REGEX_ENTREPRISE,
                        message: 'Le nom de l\'entreprise contient des caractères spéciaux non autorisés'
                    ),
                ]
            ])

            ->add('nif_entreprise', TextType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'NIF',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '123456789',
                    'maxlength' => 12,
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le NIF',
                        ),
                    new Regex(
                        pattern: self::REGEX_NIF,
                        message: 'Le NIF doit contenir uniquement entre 7 et 12 chiffres'
                    ),
                ]
            ])

            ->add('adresse_entreprise', TextType::class, [
                'mapped' => false,
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => "Adresse complète de l'entreprise",
                    'class' => 'form-control',
                    'maxlength' => 150,
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer l\'adresse',
                        ),
                    new length (
                        max: 150,
                        maxMessage: 'L\'adresse ne doit pas dépasser {{limit}} caractere'
                    ),
                    new Regex(
                        pattern: self::REGEX_ADRESSE,
                        message: 'L\'adresse contient des caractères spéciaux non autorisés'
                    ),
                ]
            ])

            ->add('tel_entreprise', TextType::class, [
                'mapped' => false,
                'label' => 'Téléphone',
                'attr' => [
                    'placeholder' => '+261 XX XX XXX XX',
                    'class' => 'form-control',
                    'maxlength' => 20,
                ],
                'constraints' => [
                    new NotBlank(
                            message: 'Veuillez entrer le telephone',
                        ),
                    new length (
                        min: 7,
                        max: 20,
                        minMessage: 'Le numéro de téléphone est trop court',
                        maxMessage: 'Le numéro de téléphone ne doit pas dépasser {{limit}} caractere'
                    ),
                    new Regex(
                        pattern: self::REGEX_TEL,
                        message: 'Le numéro de téléphone contient des caractères invalides'
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
                    'class' => 'form-control',
                    'maxlength' => 180,
                ],
                'constraints' => [
                    new NotBlank (
                        message: 'Veuillez entrer l\'email de votre entreprise'
                    ),
                    new length (
                        max: 180,
                        maxMessage: 'L\'email ne doit pas dépasser {{limit}} caractere'
                    ),
                    new Email (
                        message: "L'email {{value}} n'est pas un email valid"
                    ),
                    new RealEmail(),
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
                            min: 8,
                            minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères',
                            // max length allowed by Symfony for security reasons
                            max: 4096,
                        ),
                        new Regex(
                            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).+$/',
                            message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre'
                        ),
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
