<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Service\StripeService;
use App\Service\PaymentService;
use App\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SubscriptionRepository;
use App\Repository\ClientRepository;
use App\Service\InvoiceNumberGenerator;
use App\Entity\InvoiceStatus;
use App\Entity\Payment;
use App\Entity\PaymentProvider;
use App\Entity\PaymentStatus;

#[Route('/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
class WebhookController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private PaymentService $paymentService,
        private InvoiceService $invoiceService,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private SubscriptionRepository $subscriptionRepository,
        private ClientRepository $clientRepository,
        private InvoiceNumberGenerator $invoiceNumberGenerator
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $data = json_decode($payload, true);
        $this->logger->info('DEBUG WEBHOOK RAW: ' . ($data['type'] ?? 'unknown'));

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->stripeService->getWebhookSecret() ?? ''
            );
        } catch (\UnexpectedValueException $e) {
            $this->logger->error('Webhook Error: Invalid payload');
            return new Response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Webhook Error: Invalid signature');
            return new Response('Invalid signature', 400);
        }

        $this->logger->info('Stripe Webhook received: ' . $event->type);

        try {
            switch ($event->type) {
                case 'invoice.paid':
                    $this->stripeService->handleInvoicePaymentSucceeded($event->data->object);
                    break;
                case 'invoice.payment_failed':
                    $this->stripeService->handleInvoicePaymentFailed($event->data->object);
                    break;
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;
                case 'payment_intent.succeeded':
                    // On importe PaymentService si besoin ou on passe par StripeService
                    // Ici on utilise PaymentService via Injection si dispo,
                    // mais WebhookController n'a pas PaymentService injecté.
                    // Je vais l'ajouter au constructeur.
                    $this->paymentService->handlePaymentSuccess($event->data->object->id);
                    break;
                case 'payment_intent.payment_failed':
                    $this->paymentService->handlePaymentFailure(
                        $event->data->object->id,
                        $event->data->object->last_payment_error->message ?? 'Unknown error'
                    );
                    break;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error processing webhook: ' . $e->getMessage(), [
                'type' => $event->type,
                'exception' => $e
            ]);
        }

        return new Response('Received', 200);
    }

    private function handleCheckoutSessionCompleted(object $session): void
    {
        $this->stripeService->createOrUpdateSubscriptionFromSession($session);

        $this->logger->info('Session handled (subscription/metadata updated)');
    }

    private function handleInvoicePaymentSucceeded(object $stripeInvoice): void
    {
        $this->stripeService->handleInvoicePaymentSucceeded($stripeInvoice);
    }

    private function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        $subscription = $this->subscriptionRepository->findOneBy(['stripeSubscriptionId' => $stripeSubscription->id]);

        if ($subscription) {
            $subscription->setStatus($stripeSubscription->status);
            $subscription->setCurrentPeriodStart((new \DateTime())->setTimestamp($stripeSubscription->current_period_start));
            $subscription->setCurrentPeriodEnd((new \DateTime())->setTimestamp($stripeSubscription->current_period_end));

            $this->entityManager->flush();
            $this->logger->info('Abonnement mis à jour: ' . $subscription->getId() . ' statut: ' . $stripeSubscription->status);
        }
    }
}
