<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Création et lecture des notifications in-app (cloche du header). Volontairement générique :
 * n'importe quel module peut appeler create() pour notifier un utilisateur.
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(User $destinataire, string $type, string $titre, string $message, ?string $lien = null): Notification
    {
        $notification = new Notification();
        $notification->setDestinataire($destinataire);
        $notification->setType($type);
        $notification->setTitre($titre);
        $notification->setMessage($message);
        $notification->setLien($lien);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}
