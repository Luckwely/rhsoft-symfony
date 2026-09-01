<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Actions communes à tous les rôles pour la cloche de notifications du header
 * (marquer comme lue au clic, tout marquer comme lu).
 */
#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/{id}/lue', name: 'app_notification_marquer_lue', methods: ['POST'])]
    public function marquerLue(int $id, Request $request, NotificationRepository $repository, EntityManagerInterface $em): RedirectResponse
    {
        $notification = $repository->find($id);

        if ($notification && $notification->getDestinataire() === $this->getUser()) {
            $notification->setLue(true);
            $em->flush();
        }

        $destination = $request->request->get('redirect') ?: ($notification?->getLien() ?? $this->generateUrl('app_login'));

        return $this->redirect($destination);
    }

    #[Route('/tout-marquer-lu', name: 'app_notification_marquer_toutes_lues', methods: ['POST'])]
    public function marquerToutesLues(Request $request, NotificationRepository $repository): RedirectResponse
    {
        $repository->markAllAsReadForUser($this->getUser());

        $destination = $request->request->get('redirect') ?: $request->headers->get('referer') ?: $this->generateUrl('app_login');

        return $this->redirect($destination);
    }
}
