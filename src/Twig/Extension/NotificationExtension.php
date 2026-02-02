<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\Admin\NotificationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_notifications', [$this, 'getNotifications']),
            new TwigFunction('get_unread_notifications_count', [$this, 'getUnreadCount']),
        ];
    }

    public function getNotifications(int $limit = 8): array
    {
        return $this->notificationService->getRecentNotifications($limit);
    }

    public function getUnreadCount(): int
    {
        return $this->notificationService->getUnreadCount();
    }
}
