<?php

namespace App\EventSubscriber;

use App\Entity\SystemLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SystemLogSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        $log = new SystemLog();
        $log->setLoggedAt(new \DateTimeImmutable());
        $log->setLevel('success');
        $log->setMessage('Connexion réussie pour ' . $user->getUserIdentifier());
        $log->setUserEmail($user->getUserIdentifier());
        $log->setIpAddress($request->getClientIp());
        // If your User entity has a relation or method to get the company name, set it here:
        // $log->setCompanyName(method_exists($user, 'getCompany') ? $user->getCompany()?->getName() : null);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $email = $request->request->get('_username', 'Inconnu');

        $log = new SystemLog();
        $log->setLoggedAt(new \DateTimeImmutable());
        $log->setLevel('failed');
        $log->setMessage('Échec de connexion pour l\'email : ' . $email);
        $log->setUserEmail($email);
        $log->setIpAddress($request->getClientIp());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $log = new SystemLog();
        $log->setLoggedAt(new \DateTimeImmutable());
        // Map status code or exception type to your error levels
        $level = method_exists($exception, 'getStatusCode') && $exception->getStatusCode() >= 500 ? 'critical' : 'error';

        $log->setLevel($level);
        $log->setMessage($exception->getMessage());
        $log->setSourceFile($exception->getFile() . ' (Ligne ' . $exception->getLine() . ')');
        $log->setIpAddress($event->getRequest()->getClientIp());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
