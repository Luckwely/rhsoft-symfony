<?php

namespace App\EventSubscriber;

use App\Entity\PageView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Compte une vue du site vitrine (pages publiques, avant connexion) par
 * session de visiteur — pas à chaque requête, pour éviter de gonfler le
 * compteur au moindre rechargement ou appel d'assets.
 */
class PageViewSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getMethod() !== 'GET' || $request->isXmlHttpRequest()) {
            return;
        }

        // On ne compte que les visiteurs anonymes -> pages publiques/vitrine uniquement
        if ($this->security->getUser() !== null) {
            return;
        }

        $path = $request->getPathInfo();

        // Only the public marketing/careers site contributes to the site-view counter.
        // Login, invitation, registration, and protected application routes are excluded.
        if (!preg_match('#^/(?:$|pricing(?:/|$)|fonctionnalite(?:/|$)|carriere(?:/|$))#', $path)) {
            return;
        }

        // Ignore l'outillage technique (profiler, assets buildés, favicon...)
        if (str_starts_with($path, '/_') || str_starts_with($path, '/build') || str_starts_with($path, '/bundles')) {
            return;
        }

        // Ignore les fichiers statiques
        if (preg_match('/\.(css|js|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|map)$/i', $path)) {
            return;
        }

        // Une seule vue comptée par session visiteur
        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session->get('page_view_counted')) {
                return;
            }
            $session->set('page_view_counted', true);
        }

        $view = new PageView();
        $view->setViewedAt(new \DateTimeImmutable());

        $this->em->persist($view);
        $this->em->flush();
    }
}
