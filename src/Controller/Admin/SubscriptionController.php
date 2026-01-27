<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/subscription', name: 'admin_subscription_')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private StripeService $stripeService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $qb = $this->subscriptionRepository->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->leftJoin('s.client', 'c')
            ->addSelect('c');

        // Pagination simple (à améliorer avec KnpPaginator si dispo)
        $total = count($qb->getQuery()->getResult());
        $totalPages = ceil($total / $limit);

        $subscriptions = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/subscription/index.html.twig', [
            'subscriptions' => $subscriptions,
            'current_page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Request $request, Subscription $subscription): Response
    {
        if ($this->isCsrfTokenValid('cancel' . $subscription->getId(), $request->request->get('_token'))) {
            try {
                $this->stripeService->cancelSubscription($subscription);
                $this->addFlash('success', 'Abonnement annulé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'annulation : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_subscription_index');
    }

    #[Route('/{id}/sync', name: 'sync', methods: ['POST'])]
    public function sync(Request $request, Subscription $subscription): Response
    {
        if ($this->isCsrfTokenValid('sync' . $subscription->getId(), $request->request->get('_token'))) {
            try {
                $this->stripeService->syncSubscriptionStatus($subscription);
                $this->addFlash('success', 'Statut synchronisé depuis Stripe.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la synchronisation : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_subscription_index');
    }
}
