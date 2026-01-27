<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Subscription;
use App\Service\MagicLinkService;
use App\Service\StripeService;
use App\Service\EmailService;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SubscriptionController extends AbstractController
{
    #[Route('/public/subscription/{id}/cancel', name: 'public_subscription_cancel', methods: ['GET', 'POST'])]
    public function cancel(
        Subscription $subscription,
        Request $request,
        MagicLinkService $magicLinkService,
        StripeService $stripeService,
        EmailService $emailService,
        EntityManagerInterface $entityManager
    ): Response {
        $expires = (int) $request->query->get('expires');
        $signature = (string) $request->query->get('signature');

        if (!$magicLinkService->verifySignature('subscription', $subscription->getId(), 'cancel', $expires, $signature)) {
            throw $this->createAccessDeniedException('Lien invalide ou expiré.');
        }

        if ($request->isMethod('POST')) {
            // Logique d'annulation
            try {
                if ($subscription->getStripeSubscriptionId()) {
                    // Annuler sur Stripe
                    $stripeService->cancelSubscription($subscription);
                } else {
                    // Annuler manuellement
                    $subscription->setStatus('canceled');
                }

                $entityManager->flush();

                // Notifier l'admin
                $emailService->sendAdminNotification(
                    'Annulation d\'abonnement',
                    sprintf(
                        "Le client %s a annulé son abonnement : %s\nID local : %s\nID Stripe : %s",
                        $subscription->getClient()->getNomComplet(),
                        $subscription->getLabel(),
                        $subscription->getId(),
                        $subscription->getStripeSubscriptionId() ?? 'N/A'
                    )
                );

                $this->addFlash('success', 'Votre abonnement a été annulé avec succès.');

                return $this->redirectToRoute('public_subscription_cancel_success', [
                    'id' => $subscription->getId(),
                    'expires' => $expires,
                    'signature' => $signature
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'annulation.');
            }
        }

        return $this->render('public/subscription/cancel_confirm.html.twig', [
            'subscription' => $subscription,
            'expires' => $expires,
            'signature' => $signature
        ]);
    }

    #[Route('/public/subscription/{id}/cancel/success', name: 'public_subscription_cancel_success', methods: ['GET'])]
    public function cancelSuccess(
        Subscription $subscription,
        Request $request,
        MagicLinkService $magicLinkService
    ): Response {
        $expires = (int) $request->query->get('expires');
        $signature = (string) $request->query->get('signature');

        if (!$magicLinkService->verifySignature('subscription', $subscription->getId(), 'cancel', $expires, $signature)) {
            throw $this->createAccessDeniedException('Lien invalide ou expiré.');
        }

        return $this->render('public/subscription/cancel_success.html.twig', [
            'subscription' => $subscription
        ]);
    }
}
