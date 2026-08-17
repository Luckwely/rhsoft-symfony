<?php
namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BillingController extends AbstractController
{
    public function __construct(
        private string $stripeSecret,
        private EntityManagerInterface $em
    ) {}

    #[Route('/billing/success', name: 'app_billing_success')]
    public function success(
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $plan = $request->query->get('plan');
        $sessionId = $request->query->get('session_id');

        if (!in_array($plan, ['premium', 'vip'], true)) {
            throw $this->createNotFoundException('Plan introuvable');
        }

        if (!$sessionId) {
            $this->addFlash('danger', 'Paiement introuvable ou non confirmé.');
            return $this->redirectToRoute('app_pricing');
        }

        $user = $this->getUser();
        $entreprise = $user->getEntreprise();

        // Vérifie auprès de Stripe que la session correspond bien à cette entreprise
        // et que le paiement a réellement été effectué, avant d'activer quoi que ce soit.
        Stripe::setApiKey($this->stripeSecret);
        try {
            $session = Session::retrieve($sessionId);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Impossible de vérifier le paiement.');
            return $this->redirectToRoute('app_pricing');
        }

        if ($session->payment_status !== 'paid' || $session->customer !== $entreprise->getStripeCustomerId()) {
            $this->addFlash('danger', 'Le paiement n\'a pas pu être confirmé.');
            return $this->redirectToRoute('app_pricing');
        }

        $entreprise->setStatus('active');
        $entreprise->setPlan($plan);
        $entreprise->setDateFinAbonnement(new \DateTime($plan === 'vip' ? '+1 year' : '+1 month'));
        $em->flush();

        $this->addFlash('success', 'Abonnement activé avec succès!');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/billing/cancel', name: 'app_billing_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('danger', 'Paiement annulé');
        return $this->redirectToRoute('app_pricing');
    }

    #[Route('/billing/{plan}', name: 'app_billing', requirements: ['plan' => 'premium|vip'])]
    public function billing(string $plan): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $entreprise = $user->getEntreprise();

        $priceIds = [
            'premium' => 'price_1TmdheRbkDcc1FxK3AhBhh7D',
            'vip' => 'price_1TmdjLRbkDcc1FxKBoh10sIg',
        ];

        if (!isset($priceIds[$plan])) {
            throw $this->createNotFoundException('Plan introuvable');
        }

        if (!$entreprise->getStripeCustomerId()) {
            Stripe::setApiKey($this->stripeSecret);
            $customer = \Stripe\Customer::create([
                'email' => $user->getEmail(),
                'name' => $entreprise->getNom(),
            ]);
            
            $entreprise->setStripeCustomerId($customer->id);
            $this->em->flush();
        }

        Stripe::setApiKey($this->stripeSecret);
        $checkout = Session::create([
            'customer' => $entreprise->getStripeCustomerId(),
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $priceIds[$plan],
                'quantity' => 1,
            ]],
            'success_url' => $this->generateUrl('app_billing_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?plan=' . $plan . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->generateUrl('app_billing_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($checkout->url, 303);
    }
}
