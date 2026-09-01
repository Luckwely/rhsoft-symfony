<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\SubscriptionLimitService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Re-checks subscription access on every authenticated request (not just at
 * login). This catches the case where a trial expires while a user already
 * has an active session open.
 */
class SubscriptionAccessSubscriber implements EventSubscriberInterface
{
    // Routes that must always remain reachable, even for an expired/blocked account.
    private const ALLOWED_ROUTES = [
        'app_login',
        'app_logout',
        'app_register',
        'app_home',
        'app_pricing',
        'app_billing',
        'app_billing_success',
        'app_billing_cancel',
        'app_verify_email',
        'app_invite_accept',
        'app_stripe_webhook',
    ];

    public function __construct(
        private Security $security,
        private SubscriptionLimitService $subscriptionLimitService,
        private UrlGeneratorInterface $router,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 8]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');

        if ($routeName === null || in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return; // not logged in, nothing to enforce here
        }

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $entreprise = $user->getEntreprise();

        if (!$this->subscriptionLimitService->isAccessAllowed($entreprise)) {
            $reason = $this->subscriptionLimitService->getAccessDeniedReason($entreprise);
            $session = $request->getSession();

            $session->invalidate();
            $session->getFlashBag()->add('danger', $reason);

            $response = new RedirectResponse($this->router->generate('app_login'));
            $event->setResponse($response);
        }
    }
}
