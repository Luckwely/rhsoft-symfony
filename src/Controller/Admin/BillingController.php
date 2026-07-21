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
    public function success(Request $request, EntityManagerInterface $em): Response
    {
        $plan = $request->query->get('plan');

        if (!in_array($plan, ['premium', 'vip'])) {
            throw $this->createNotFoundException('Plan introuvable');
        }

        $user = $this->getUser();
        $entreprise = $user->getEntreprise();
        $entreprise->setStatus('active');
        $entreprise->setPlan($plan);
        $entreprise->setDateFinAbonnement(new \DateTime($plan === 'vip' ? '+1 year' : '+1 month'));
        $em->flush();

        $this->addFlash('success', 'Abonnement activé avec succès!');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/billing/cancel', name: 'app_billing_cancel')]
    public function cancel(): Response
    {
        $this->addFlash('danger', 'Paiement annulé');
        return $this->redirectToRoute('app_pricing');
    }

    // 2. DYNAMIC ROUTE LAST
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
