<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository
    ) {

    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $request->getSession()->set('_security.last_username', $email);

        return new Passport(
            new UserBadge($email, fn($userIdentifier) => $this->getUser($userIdentifier)),
            new PasswordCredentials($request->request->get('password', '')),
            [new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token'))]
        );
    }

    private function getUser(string $email)
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            throw new UserNotFoundException();
        }
        if (!$user->isActive()) {
            if ($user->getEmailVerifiedAt() === null) {
                throw new CustomUserMessageAuthenticationException('Veuillez confirmer votre email avant de vous connecter.');
            }

            $entreprise = $user->getEntreprise();
            if ($entreprise && !in_array($entreprise->getStatus(), ['active', 'trial'], true)) {
                throw new CustomUserMessageAuthenticationException('Votre entreprise a été suspendue. Merci de contacter votre administrateur ou notre support.');
            }

            throw new CustomUserMessageAuthenticationException('Votre compte a été désactivé. Merci de contacter votre administrateur.');
        }
        return $user;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $user = $token->getUser();

        // Redirection selon le rôle
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return new RedirectResponse($this->urlGenerator->generate('app_super_admin_dashboard'));
        }
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }
        if (in_array('ROLE_RH', $user->getRoles())) {
            return new RedirectResponse($this->urlGenerator->generate('app_rh_dashboard'));
        }
        if (in_array('ROLE_MANAGER', $user->getRoles())) {
            return new RedirectResponse($this->urlGenerator->generate('app_manager_profile_solde'));
        }

        // Par défaut ROLE_USER / Employe
        return new RedirectResponse($this->urlGenerator->generate('app_employe_profile'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
