<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Entity\Entreprise;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Security\LoginFormAuthenticator;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

//use Stripe\Stripe;
//use Stripe\Customer;
//use Stripe\Exception\ApiErrorException;


class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier, private string $stripeSecret)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $plan = $request->query->get('plan', 'essai');
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entreprise = new Entreprise();
            $nomEntreprise = $form->get('nom_entreprise')->getData();
            $entreprise->setNom($nomEntreprise);
            $entreprise->setNif($form->get('nif_entreprise')->getData());
            $entreprise->setAdresse($form->get('adresse_entreprise')->getData());
            $entreprise->setTel($form->get('tel_entreprise')->getData());
            $entreprise->setEmail($user->getEmail());

            // 2. SET PLAN BASED ON WHAT USER CLICKED
            if ($plan === 'essai') {
                $entreprise->setStatus('trial');
                $entreprise->setPlan('essai');
                $entreprise->setDateFinAbonnement(new \DateTime('+14 days'));
            } else {
                $entreprise->setStatus('pending'); // waiting for payment
                $entreprise->setPlan($plan);
            }

            //$entreprise->setStatus('trial');
            //$entreprise->setPlan('essai');
            //$entreprise->setDateFinAbonnement(new \DateTime('+14 days'));
            $entreprise->setSlug($this->generateSlug($nomEntreprise));
            $entreprise->setCreatedAt(new \DateTime());

            /** @var UploadedFile $logoFile */
            $logoFile = $form->get('logo_entreprise')->getData();
            if ($logoFile) {
                $newFilename = $slugger->slug($entreprise->getNom()).'-'.uniqid().'.'.$logoFile->guessExtension();
                $logoFile->move($this->getParameter('logos_directory'), $newFilename);
                $entreprise->setLogo($newFilename);
            }

            $entityManager->persist($entreprise);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $user->setEntreprise($entreprise);
            $user->setRoles(['ROLE_ADMIN']);
            $user->setIsActive(false);
            $user->setIsVerified(false);
            $user->setEmailVerifiedAt(null);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('noreply@rhsoft.test', 'Rhsoft'))
                    ->to((string) $user->getEmail())
                    ->subject('Veuillez confirmer votre email')
                    ->htmlTemplate('auth/registration/confirmation_email.html.twig')
                    ->context([
                        'user' => $user,
                    ])
            );

            $this->addFlash('success', 'Un email de confirmation vous a été envoyé.');

            return $this->redirectToRoute('app_check_email', [
                'email' => $user->getEmail()
            ]);
        }

        return $this->render('auth/registration/register.html.twig', [
            'registrationForm' => $form,
            'plan' => $plan
        ]);
    }

    private function generateSlug(string $nom): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nom)));
        return $slug . '-' . uniqid(); // évite les doublons: rhsoft-68f1a2b3c4d5
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail
        (
            Request $request,
            TranslatorInterface $translator,
            EntityManagerInterface $entityManager,
            LoginFormAuthenticator $authenticator,
            UserAuthenticatorInterface $userAuthenticator,
        ): Response
    {
        //$this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // validate email confirmation link, sets User::isVerified=true and persists

        $id = $request->query->get('id');

        if (null === $id) {
            $this->addFlash('danger','Utilisateur introuvable');
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        try {

            /** @var User $user */

            //$user = $this->getUser();

            $this->emailVerifier->handleEmailConfirmation($request, $user);

        } catch (VerifyEmailExceptionInterface $exception) {

            $this->addFlash('danger',$exception->getReason());

            //$this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Email verifié! Vous pouvez vous connecter');

        $user->setIsActive(true);
        $user->setIsVerified(true);
        $user->setEmailVerifiedAt(new \DateTime());
        $entityManager->flush();

        $userAuthenticator->authenticateUser($user, $authenticator, $request);

        $plan = $user->getEntreprise()->getPlan();

            if ($plan !== 'essai') {
                return $this->redirectToRoute('app_billing', [
                    'plan' => $plan
                ]);
            }

        return $this->redirectToRoute('app_dashboard'); // trial goes to dashboard
        //return $this->redirectToRoute('app_login');
    }

    #[Route('/register/check-email', name: 'app_check_email')]
    public function checkEmail(Request $request)
    {
        $email = $request->query->get('email');

        return $this->render('auth/registration/check_email.html.twig', [
            'user' => [
                'email' => $email
            ]
        ]);
    }
}
