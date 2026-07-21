<?php

namespace App\Controller\Admin;

use App\Entity\Entreprise;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    #[Route('/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function webhook(Request $request, EntityManagerInterface $em): Response
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

                $entreprise = $em->getRepository(Entreprise::class)->findOneBy(['stripeCustomerId' => $customerId]);

                if ($entreprise) {
                    $entreprise->setStatus('active');
                    $entreprise->setPlan('pro');
                    $entreprise->setStripeSubscriptionId($subscriptionId);
                    $entreprise->setDateFinAbonnement((new \DateTime())->modify('+1 month'));
                    $em->flush();
                }
                break;

            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
                $entreprise = $em->getRepository(Entreprise::class)->findOneBy(['stripeSubscriptionId' => $subscription->id]);
                if ($entreprise) {
                    $entreprise->setStatus('canceled');
                    $em->flush();
                }
                break;
        }

        return new Response('Webhook received', 200);
    }
}
