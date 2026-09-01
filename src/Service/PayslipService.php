<?php

namespace App\Service;

use App\Entity\Entreprise;
use App\Entity\Paie;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 *
 * 
 */
class PayslipService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Génère le PDF de la fiche de paie (contenu binaire).
     */
    public function generatePdf(Paie $paie): string
    {
        $employee = $paie->getEmployee();

        $html = $this->twig->render('employe/paie/payslip_pdf.html.twig', [
            'payslip' => $paie,
            'employee' => $employee,
            'entreprise' => $employee?->getEntreprise(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function buildFilename(Paie $paie): string
    {
        return sprintf('fiche-de-paie-%02d-%d.pdf', $paie->getMois(), $paie->getAnnee());
    }

    /**
     * Envoie automatiquement la fiche de paie (PDF joint) à l'employé par email.
     * Ne fait rien silencieusement si l'employé n'a pas d'adresse email valide :
     * l'appelant est responsable de vérifier la valeur de retour et d'informer l'utilisateur.
     *
     * @throws \Exception si l'envoi échoue (transport, etc.)
     */
    public function sendPayslipByEmail(Paie $paie): bool
    {
        $employee = $paie->getEmployee();
        if (!$employee || !$employee->getEmail()) {
            return false;
        }

        $pdfContent = $this->generatePdf($paie);
        $entreprise = $employee->getEntreprise();

        $email = (new Email())
            ->from(new Address('no-reply@rhsoft.mg', $entreprise?->getNom() ?? 'RhSoft'))
            ->to($employee->getEmail())
            ->subject(sprintf('Votre fiche de paie - %02d/%d', $paie->getMois(), $paie->getAnnee()))
            ->html($this->twig->render('emails/payslip_notification.html.twig', [
                'employee' => $employee,
                'payslip' => $paie,
                'entreprise' => $entreprise,
                'entrepriseLogoUrl' => $this->buildEntrepriseLogoUrl($entreprise),
            ]))
            ->attach($pdfContent, $this->buildFilename($paie), 'application/pdf');

        $this->mailer->send($email);

        return true;
    }

    /**
     * Construit une URL absolue vers le logo de l'entreprise, requise dans un email
     * (contrairement au HTML web, les clients mail ne résolvent pas les chemins relatifs).
     * Retourne null si l'entreprise n'a pas de logo ou si aucun contexte de requête
     * n'est disponible (ex. exécution en ligne de commande) pour déterminer l'hôte.
     */
    private function buildEntrepriseLogoUrl(?Entreprise $entreprise): ?string
    {
        if (!$entreprise?->getLogo()) {
            return null;
        }

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

        return sprintf('%s://%s%s/uploads/logos/%s', $scheme, $context->getHost(), $port, $entreprise->getLogo());
    }
}
