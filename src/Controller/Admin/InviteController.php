<?php
namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class InviteController extends AbstractController
{
    #[Route('/invite/accept/{token}', name: 'app_invite_accept')]
    public function accept(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $em->getRepository(User::class)->findOneBy(['invitationToken' => $token]);

        if (!$user) {
            $this->addFlash('danger', 'Lien invalide ou déjà utilisé.');
            return $this->redirectToRoute('app_login');
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($user->isActive()) {
            $this->addFlash('info', 'Votre compte est déjà activé. Connectez-vous.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getInvitationExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('danger', 'Lien expiré. Demandez une nouvelle invitation.');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('password_confirm');

            if (strlen($password) < 8) {
                $this->addFlash('danger', 'Le mot de passe doit faire au moins 8 caractères.');
            } elseif ($password !== $passwordConfirm) { // <-- Only 1 time
                $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
            } else {
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);
                $user->setIsActive(true);
                $user->setFirstLogin(true);
                $user->setInvitationToken(null);
                $user->setInvitationExpiresAt(null);
                $em->flush();

                $this->addFlash('success', 'Compte activé ! Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('invite/accept.html.twig', [
            'user' => $user
        ]);
    }
}
