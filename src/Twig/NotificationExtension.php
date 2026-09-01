<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fonctions Twig exposant les notifications in-app au header (partagé par tous les rôles),
 * pour éviter d'avoir à les passer explicitement depuis chaque contrôleur/action.
 */
class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notifications_non_lues_count', [$this, 'countUnread']),
            new TwigFunction('notifications_recentes', [$this, 'recent']),
        ];
    }

    public function countUnread(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        return $this->notificationRepository->countUnreadByUser($user);
    }

    /** @return \App\Entity\Notification[] */
    public function recent(?User $user, int $limit = 8): array
    {
        if (!$user) {
            return [];
        }

        return $this->notificationRepository->findRecentByUser($user, $limit);
    }
}
