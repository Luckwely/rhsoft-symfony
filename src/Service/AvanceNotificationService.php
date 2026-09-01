<?php

namespace App\Service;

use App\Entity\AvanceSalaire;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Notifie (email + in-app) les personnes concernées par le cycle de vie d'une demande
 * d'avance sur salaire : soumission, approbation, rejet, paiement effectif.
 * Un échec d'envoi d'email n'interrompt jamais le flux métier appelant (approuver/rejeter/
 * payer doit réussir même si le transport mail est indisponible) ; la notice in-app,
 * elle, est toujours créée.
 */
class AvanceNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function notifierSoumission(AvanceSalaire $avance): void
    {
        $employee = $avance->getEmployee();
        $entreprise = $avance->getEntreprise();

        $this->envoyer(
            $employee,
            $avance,
            Notification::TYPE_AVANCE_SOUMISE,
            'Demande d\'avance envoyée',
            sprintf('Votre demande d\'avance de %s Ar a été envoyée et est en attente de validation RH.', number_format($avance->getMontant(), 0, ',', ' ')),
            $this->lienEspacePersonnel($employee)
        );

        foreach ($this->userRepository->findRhByEntreprise($entreprise, $employee) as $rh) {
            $this->envoyer(
                $rh,
                $avance,
                Notification::TYPE_AVANCE_SOUMISE,
                'Nouvelle demande d\'avance à traiter',
                sprintf('%s %s a soumis une demande d\'avance de %s Ar à traiter.', $employee->getPrenom(), $employee->getNom(), number_format($avance->getMontant(), 0, ',', ' ')),
                $this->lienRoute('app_rh_demandes_avance')
            );
        }
    }

    public function notifierApprobation(AvanceSalaire $avance): void
    {
        $employee = $avance->getEmployee();

        $this->envoyer(
            $employee,
            $avance,
            Notification::TYPE_AVANCE_APPROUVEE,
            'Demande d\'avance approuvée',
            sprintf('Votre demande d\'avance de %s Ar a été approuvée. Le versement effectif reste à venir.', number_format($avance->getMontant(), 0, ',', ' ')),
            $this->lienEspacePersonnel($employee)
        );
    }

    public function notifierRejet(AvanceSalaire $avance): void
    {
        $employee = $avance->getEmployee();

        $message = sprintf('Votre demande d\'avance de %s Ar a été rejetée.', number_format($avance->getMontant(), 0, ',', ' '));
        if ($avance->getMotif()) {
            $message .= sprintf(' Motif : %s', $avance->getMotif());
        }

        $this->envoyer(
            $employee,
            $avance,
            Notification::TYPE_AVANCE_REJETEE,
            'Demande d\'avance rejetée',
            $message,
            $this->lienEspacePersonnel($employee)
        );
    }

    public function notifierPaiement(AvanceSalaire $avance): void
    {
        $employee = $avance->getEmployee();

        $this->envoyer(
            $employee,
            $avance,
            Notification::TYPE_AVANCE_PAYEE,
            'Avance versée',
            sprintf('Votre avance de %s Ar a été versée. Elle sera déduite de votre prochain salaire net.', number_format($avance->getMontant(), 0, ',', ' ')),
            $this->lienEspacePersonnel($employee)
        );
    }

    /**
     * Crée la notice in-app (toujours) puis tente l'envoi de l'email correspondant
     * (best-effort : une erreur de transport ne doit pas casser le flux appelant).
     */
    private function envoyer(User $destinataire, AvanceSalaire $avance, string $type, string $titre, string $message, string $lienRelatif): void
    {
        $this->notificationService->create($destinataire, $type, $titre, $message, $lienRelatif);

        try {
            $this->envoyerEmail($destinataire, $avance, $titre, $message, $lienRelatif);
        } catch (\Throwable $e) {
            // L'échec d'envoi n'interrompt jamais le flux métier ; la notice in-app existe déjà.
        }
    }

    private function envoyerEmail(User $destinataire, AvanceSalaire $avance, string $titre, string $message, string $lienRelatif): void
    {
        if (!$destinataire->getEmail()) {
            return;
        }

        $entreprise = $avance->getEntreprise();

        $email = (new Email())
            ->from(new Address('no-reply@rhsoft.mg', $entreprise?->getNom() ?? 'RhSoft'))
            ->to($destinataire->getEmail())
            ->subject($titre)
            ->html($this->twig->render('emails/avance_notification.html.twig', [
                'destinataire' => $destinataire,
                'avance' => $avance,
                'entreprise' => $entreprise,
                'titre' => $titre,
                'message' => $message,
                'lienAbsolu' => $this->buildAbsoluteUrl($lienRelatif),
            ]));

        $this->mailer->send($email);
    }

    private function lienEspacePersonnel(User $user): string
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_RH', $roles, true)) {
            return $this->lienRoute('app_rh_avance');
        }

        if (in_array('ROLE_MANAGER', $roles, true)) {
            return $this->lienRoute('app_manager_avance');
        }

        return $this->lienRoute('app_employe_demandes_avance');
    }

    private function lienRoute(string $routeName): string
    {
        return $this->urlGenerator->generate($routeName);
    }

    /**
     * Un email a besoin d'une URL absolue (contrairement au HTML web, les clients mail ne
     * résolvent pas les chemins relatifs). Retourne null si aucun contexte de requête n'est
     * disponible (ex. exécution en ligne de commande).
     */
    private function buildAbsoluteUrl(string $lienRelatif): ?string
    {
        $context = $this->urlGenerator->getContext();
        if (!$context->getHost()) {
            return null;
        }

        $scheme = $context->getScheme() ?: 'https';
        $port = '';
        if ('http' === $scheme && 80 !== $context->getHttpPort()) {
            $port = ':'.$context->getHttpPort();
        } elseif ('https' === $scheme && 443 !== $context->getHttpsPort()) {
            $port = ':'.$context->getHttpsPort();
        }

        return sprintf('%s://%s%s%s', $scheme, $context->getHost(), $port, $lienRelatif);
    }
}
