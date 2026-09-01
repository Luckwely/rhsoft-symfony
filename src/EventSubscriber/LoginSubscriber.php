<?php

namespace App\EventSubscriber;

use App\Service\SubscriptionLimitService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $router,
        private SubscriptionLimitService $subscriptionLimitService,
    ) {
    }

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

        if (!$this->subscriptionLimitService->isAccessAllowed($entreprise)) {
            $reason = $this->subscriptionLimitService->getAccessDeniedReason($entreprise);
            $session = $event->getRequest()->getSession();

            // Déconnecte et redirige, avec un message d'erreur affiché sur la page de login
            $session->invalidate();
            $session->getFlashBag()->add('danger', $reason);

            $response = new RedirectResponse($this->router->generate('app_login'));
            $event->setResponse($response);
        }
    }
}
