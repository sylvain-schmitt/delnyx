<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PaymentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour gérer les webhooks Stripe
 * 
 * Reçoit les événements de paiement de Stripe et les traite
 * en toute sécurité (vérification de signature).
 */
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private PaymentService $paymentService,
        private LoggerInterface $logger,
        private string $stripeWebhookSecret = '',
    ) {
    }

    #[Route('/webhook/stripe', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        if (empty($this->stripeWebhookSecret)) {
            $this->logger->error('Stripe Webhook Secret not configured');
            return new Response('Webhook secret not configured', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            // Vérifier la signature du webhook
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->stripeWebhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            // Payload invalide
            $this->logger->error('Invalid webhook payload: ' . $e->getMessage());
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Signature invalide
            $this->logger->error('Invalid webhook signature: ' . $e->getMessage());
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        // Traiter l'événement selon son type
        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
                default => $this->logger->info('Unhandled webhook event type: ' . $event->type),
            };

            return new Response('Webhook handled', Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook: ' . $e->getMessage(), [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'exception' => $e,
            ]);
            return new Response('Error processing webhook', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Gère l'événement checkout.session.completed
     * Déclenché quand un utilisateur termine le paiement sur Stripe Checkout
     */
    private function handleCheckoutSessionCompleted(\Stripe\Event $event): void
    {
        $session = $event->data->object;

        $this->logger->info('Checkout session completed', [
            'session_id' => $session->id,
            'payment_intent' => $session->payment_intent,
        ]);

        // Récupérer le PaymentIntent ID
        $paymentIntentId = $session->payment_intent;

        if ($paymentIntentId) {
            $this->paymentService->handlePaymentSuccess($paymentIntentId);
        } else {
            $this->logger->warning('Checkout session without payment_intent', [
                'session_id' => $session->id,
            ]);
        }
    }

    /**
     * Gère l'événement payment_intent.succeeded
     * Déclenché quand un paiement est confirmé avec succès
     */
    private function handlePaymentIntentSucceeded(\Stripe\Event $event): void
    {
        $paymentIntent = $event->data->object;

        $this->logger->info('Payment intent succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
        ]);

        $this->paymentService->handlePaymentSuccess($paymentIntent->id);
    }

    /**
     * Gère l'événement payment_intent.payment_failed
     * Déclenché quand un paiement échoue
     */
    private function handlePaymentIntentFailed(\Stripe\Event $event): void
    {
        $paymentIntent = $event->data->object;
        $failureMessage = $paymentIntent->last_payment_error->message ?? 'Unknown error';

        $this->logger->warning('Payment intent failed', [
            'payment_intent_id' => $paymentIntent->id,
            'failure_message' => $failureMessage,
        ]);

        $this->paymentService->handlePaymentFailure($paymentIntent->id, $failureMessage);
    }
}
