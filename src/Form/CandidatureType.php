<?php
namespace App\Form;

use App\Entity\Candidature;
use App\Validator\Constraints\RealEmail;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class CandidatureType extends AbstractType
{
    private const REGEX_NOM = '/^[\p{L}\s\'-]+$/u';
    private const REGEX_TEL = '/^[0-9+\s().-]+$/';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['maxlength' => 50],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire'),
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
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['maxlength' => 50],
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire'),
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
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['maxlength' => 180],
                'constraints' => [
                    new NotBlank(message: 'L\'email est obligatoire'),
                    new Length(
                        max: 180,
                        maxMessage: 'L\'email ne doit pas dépasser {{ limit }} caractères'
                    ),
                    new Email(message: 'Email invalide'),
                    new RealEmail(),
                ],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'attr' => ['maxlength' => 20],
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
                ],
            ])
            ->add('cv', FileType::class, [
                'label' => 'CV PDF',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Merci d\'uploader un PDF'
                    )
                ],
            ])
            ->add('lettreMotivation', FileType::class, [
                'label' => 'Lettre de motivation (PDF)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotNull(message: 'La lettre de motivation est obligatoire.'),
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Merci d\'uploader un PDF'
                    )
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Candidature::class,
        ]);
    }
}
