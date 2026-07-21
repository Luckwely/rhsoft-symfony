<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(private UrlGeneratorInterface $router) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return;
        }
        
        $entreprise = $user->getEntreprise();

        if (!$entreprise || !in_array($entreprise->getStatus(), ['trial', 'active'])) {
            // Déconnecte et redirige
            $event->getRequest()->getSession()->invalidate();
            $response = new RedirectResponse($this->router->generate('app_login'));
            $response->headers->set('X-Auth-Error', 'Votre entreprise est '.$entreprise->getStatus());
            $event->setResponse($response);
        }
    }
}
