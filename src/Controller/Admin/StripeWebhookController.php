<?php

namespace App\Controller\Admin;

use App\Entity\Entreprise;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    /**
     * Must stay in sync with the price IDs used in BillingController::billing().
     */
    private const PRICE_TO_PLAN = [
        'price_1TmdheRbkDcc1FxK3AhBhh7D' => 'premium',
        'price_1TmdjLRbkDcc1FxKBoh10sIg' => 'vip',
    ];

    public function __construct(private string $stripeSecret)
    {
    }

    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $endpointSecret = $_ENV['STRIPE_WEBHOOK_SECRET'];
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('stripe-signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException $e) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return new Response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $customerId = $session->customer;
                $subscriptionId = $session->subscription;

                $entreprise = $em->getRepository(Entreprise::class)->findOneBy(['stripe_customer_id' => $customerId]);

                if ($entreprise && $subscriptionId) {
                    $plan = $this->resolvePlanFromSubscription($subscriptionId);

                    if ($plan !== null) {
                        $entreprise->setStatus('active');
                        $entreprise->setPlan($plan);
                        $entreprise->setStripeSubscriptionId($subscriptionId);
                        // Même règle que BillingController::success() : VIP se facture annuellement, Premium mensuellement.
                        $entreprise->setDateFinAbonnement((new \DateTime())->modify($plan === 'vip' ? '+1 year' : '+1 month'));
                        $em->flush();
                    }
                }
                break;

            case 'customer.subscription.updated':
                $subscription = $event->data->object;
                $entreprise = $em->getRepository(Entreprise::class)->findOneBy(['stripe_subscription_id' => $subscription->id]);

                if ($entreprise) {
                    $plan = $this->resolvePlanFromSubscription($subscription);

                    if ($plan !== null) {
                        $entreprise->setPlan($plan);
                    }

                    // Un renouvellement raté (carte refusée, etc.) repasse Stripe en 'past_due' / 'unpaid' :
                    // on bloque l'accès jusqu'à régularisation, sans perdre le plan choisi.
                    $entreprise->setStatus(in_array($subscription->status, ['active', 'trialing'], true) ? 'active' : 'pending');
                    $em->flush();
                }
                break;

            case 'invoice.payment_failed':
                $invoice = $event->data->object;
                $entreprise = $em->getRepository(Entreprise::class)->findOneBy(['stripe_customer_id' => $invoice->customer]);

                if ($entreprise) {
                    $entreprise->setStatus('pending');
                    $em->flush();
                }
                break;

            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
                $entreprise = $em->getRepository(Entreprise::class)->findOneBy(['stripe_subscription_id' => $subscription->id]);
                if ($entreprise) {
                    $entreprise->setStatus('canceled');
                    $em->flush();
                }
                break;
        }

        return new Response('Webhook received', 200);
    }

    /**
     * Resolves our internal plan slug ('premium'|'vip') from a Stripe subscription's
     * price ID, using the same PRICE_TO_PLAN map BillingController uses to create the
     * checkout session in the first place. Returns null for an unrecognized price
     * (e.g. a price removed/changed in the Stripe dashboard without updating this map).
     */
    private function resolvePlanFromSubscription(Subscription|string $subscription): ?string
    {
        if (is_string($subscription)) {
            Stripe::setApiKey($this->stripeSecret);
            try {
                $subscription = Subscription::retrieve($subscription);
            } catch (\Exception $e) {
                return null;
            }
        }

        $priceId = $subscription->items->data[0]->price->id ?? null;

        return $priceId !== null ? (self::PRICE_TO_PLAN[$priceId] ?? null) : null;
    }
}
