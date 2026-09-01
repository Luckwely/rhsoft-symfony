<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\Constraints\RealEmail;
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
    File,
    Length,
    Regex,
    Range,
    LessThanOrEqual,
    GreaterThanOrEqual
};

class EmployeeFormType extends AbstractType
{
    // Lettres (avec accents), espaces, tirets et apostrophes uniquement
    private const REGEX_NOM = '/^[\p{L}\s\'-]+$/u';

    // Chiffres, espaces, +, - et parenthèses (numéros locaux et internationaux)
    private const REGEX_TEL = '/^[0-9+\s().-]+$/';

    // Bloque les balises/scripts et caractères dangereux, autorise ponctuation courante
    private const REGEX_TEXTE_SUR = '/^[\p{L}0-9\s.,\'°\/-]+$/u';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'placeholder' => 'Votre prénom',
                    'class' => 'form-control',
                    'maxlength' => 50,
                ],
                'constraints' => [
                    new NotBlank(
                        message: "Le prénom est obligatoire"
                    ),
                    new Length(
                        min: 2,
                        max: 50,
                        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Le prénom ne doit pas dépasser {{ limit }} caractères'
                    ),
                    new Regex(
                        pattern: self::REGEX_NOM,
                        message: 'Le prénom ne doit contenir que des lettres, espaces, tirets ou apostrophes'
                    ),
                ]
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Votre nom',
                    'class' => 'form-control',
                    'maxlength' => 50,
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Le nom est obligatoire'
                    ),
                    new Length(
                        min: 2,
                        max: 50,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
                        maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères'
                    ),
                    new Regex(
                        pattern: self::REGEX_NOM,
                        message: 'Le nom ne doit contenir que des lettres, espaces, tirets ou apostrophes'
                    ),
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'placeholder' => 'Votre email',
                    'class' => 'form-control',
                    'maxlength' => 180,
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Email obligatoire'
                    ),
                    new Length(
                        max: 180,
                        maxMessage: 'L\'email ne doit pas dépasser {{ limit }} caractères'
                    ),
                    new Email(
                        message: 'Email invalide'
                    ),
                    new RealEmail(),
                ]
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Telephone',
                'attr' => [
                    'placeholder' => 'Votre numero de telephone',
                    'class' => 'form-control',
                    'maxlength' => 20,
                ],
                'required' => false,
                'constraints' => [
                    new Length(
                        min: 7,
                        max: 20,
                        minMessage: 'Le numéro de téléphone est trop court',
                        maxMessage: 'Le numéro de téléphone ne doit pas dépasser {{ limit }} caractères'
                    ),
                    new Regex(
                        pattern: self::REGEX_TEL,
                        message: 'Le numéro de téléphone contient des caractères invalides'
                    ),
                ]
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => 'Votre adresse',
                    'class' => 'form-control',
                    'maxlength' => 150,
                ],
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 150,
                        maxMessage: 'L\'adresse ne doit pas dépasser {{ limit }} caractères'
                    ),
                    new Regex(
                        pattern: self::REGEX_TEXTE_SUR,
                        message: 'L\'adresse contient des caractères spéciaux non autorisés'
                    ),
                ]
            ])
            ->add('genre', ChoiceType::class, [
                'label' => 'Genre',
                'attr' => [
                    'class' => 'form-control'
                ],
                'choices' => [
                    'Homme' => 'H',
                    'Femme' => 'F',
                ],
                'placeholder' => 'Non renseigné',
                'required' => false,
            ])
            ->add('dateNaissance', DateType::class, [
                'label' => 'Date de naissance',
                'attr' => [
                    'class' => 'form-control',
                    // bloque la sélection dans le widget natif au-delà de 18 ans en arrière
                    'max' => (new \DateTimeImmutable('-18 years'))->format('Y-m-d'),
                    'min' => (new \DateTimeImmutable('-100 years'))->format('Y-m-d'),
                ],
                'widget' => 'single_text',
                'required' => false,
                'constraints' => [
                    new LessThanOrEqual(
                        value: (new \DateTimeImmutable('-18 years'))->format('Y-m-d'),
                        message: 'L\'employé doit avoir au moins 18 ans'
                    ),
                    new GreaterThanOrEqual(
                        value: (new \DateTimeImmutable('-100 years'))->format('Y-m-d'),
                        message: 'La date de naissance n\'est pas réaliste'
                    ),
                ],
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
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez sélectionner un rôle'
                    )
                ]
            ])
            ->add('poste', TextType::class, [
                'label' => 'Poste',
                'attr' => [
                    'placeholder' => 'Ex: Développeur Junior',
                    'class' => 'form-control',
                    'maxlength' => 80,
                ],
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 80,
                        maxMessage: 'Le poste ne doit pas dépasser {{ limit }} caractères'
                    ),
                    new Regex(
                        pattern: self::REGEX_TEXTE_SUR,
                        message: 'Le poste contient des caractères spéciaux non autorisés'
                    ),
                ]
            ])
            ->add('service', TextType::class, [
                'label' => 'Service',
                'attr' => [
                    'placeholder' => 'Ex: IT',
                    'class' => 'form-control',
                    'maxlength' => 80,
                ],
                'required' => false,
                'constraints' => [
                    new Length(
                        max: 80,
                        maxMessage: 'Le service ne doit pas dépasser {{ limit }} caractères'
                    ),
                    new Regex(
                        pattern: self::REGEX_TEXTE_SUR,
                        message: 'Le service contient des caractères spéciaux non autorisés'
                    ),
                ]
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
                    'class' => 'form-control',
                    'max' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                ],
                'widget' => 'single_text',
                'required' => false,
                'constraints' => [
                    new LessThanOrEqual(
                        value: 'today',
                        message: 'La date d\'embauche ne peut pas être dans le futur'
                    ),
                ],
            ])

            ->add('soldeConge', NumberType::class, [
                'label' => 'Solde congé',
                'data' => $options['data']->getSoldeConge() ?? 2.5,
                'attr' => [
                    'placeholder' => 'Votre nombre congé',
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 60,
                    'step' => 0.5,
                ],
                'required' => false,
                'constraints' => [
                    new Range(
                        min: 0,
                        max: 60,
                        notInRangeMessage: 'Le solde congé doit être compris entre {{ min }} et {{ max }}'
                    ),
                ]
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
                    return $roleString? [$roleString] : ['ROLE_EMPLOYE'];
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
