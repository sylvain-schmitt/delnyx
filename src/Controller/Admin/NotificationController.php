<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Admin\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Redirige vers le dashboard car la page de listing n'est plus utilisée
     */
    #[Route('', name: 'admin_notification_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_dashboard');
    }

    /**
     * Marquer une notification comme lue (AJAX)
     */
    #[Route('/{key}/read', name: 'admin_notification_read', methods: ['POST'])]
    public function markAsRead(string $key): JsonResponse
    {
        $this->notificationService->markAsRead($key);

        return new JsonResponse([
            'success' => true,
            'unreadCount' => $this->notificationService->getUnreadCount()
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues (AJAX)
     */
    #[Route('/read-all', name: 'admin_notification_read_all', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        $this->notificationService->markAllAsRead();

        return new JsonResponse([
            'success' => true,
            'unreadCount' => 0
        ]);
    }

    /**
     * Masquer une notification (AJAX)
     */
    #[Route('/{key}/hide', name: 'admin_notification_hide', methods: ['POST'])]
    public function hide(string $key): JsonResponse
    {
        $this->notificationService->hideNotification($key);

        return new JsonResponse([
            'success' => true,
            'unreadCount' => $this->notificationService->getUnreadCount()
        ]);
    }

    /**
     * Masquer toutes les notifications (AJAX)
     */
    #[Route('/hide-all', name: 'admin_notification_hide_all', methods: ['POST'])]
    public function hideAll(): JsonResponse
    {
        $this->notificationService->hideAll();

        return new JsonResponse([
            'success' => true,
            'unreadCount' => 0
        ]);
    }
}
