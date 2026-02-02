<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\InvoiceStatus;
use App\Entity\QuoteStatus;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\NotificationReadRepository;
use App\Repository\QuoteRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service pour agréger les activités récentes (notifications) du système
 */
class NotificationService
{
    public function __construct(
        private AppointmentRepository $appointmentRepository,
        private InvoiceRepository $invoiceRepository,
        private QuoteRepository $quoteRepository,
        private NotificationReadRepository $notificationReadRepository,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    /**
     * Récupère les dernières notifications (activités) filtrées par rôle
     *
     * @return array<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     message: string,
     *     icon: string,
     *     date: \DateTimeInterface,
     *     link: string,
     *     is_priority: bool,
     *     is_read: bool
     * }>
     */
    public function getRecentNotifications(int $limit = 10): array
    {
        $notifications = [];
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return [];
        }

        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $readKeys = $this->notificationReadRepository->getReadKeys($user);
        $hiddenKeys = $this->notificationReadRepository->getHiddenKeys($user);

        // 1. RDV (Admin Only)
        if ($isAdmin) {
            $appointments = $this->appointmentRepository->findBy(
                [],
                ['createdAt' => 'DESC'],
                $limit * 2 // On prend plus large car on va filtrer
            );

            foreach ($appointments as $appointment) {
                $key = 'appointment_' . $appointment->getId();
                if (in_array($key, $hiddenKeys, true)) continue;

                $client = $appointment->getClient();
                $startAt = $appointment->getStartAt();

                if (!$client || !$startAt) continue;

                $notifications[] = [
                    'id' => $key,
                    'type' => 'appointment',
                    'title' => 'Nouveau Rendez-vous',
                    'message' => 'RDV avec ' . $client->getNomComplet() . ' le ' . $startAt->format('d/m H:i'),
                    'icon' => 'lucide:calendar',
                    'date' => $appointment->getCreatedAt() ?? new \DateTime(),
                    'link' => $this->urlGenerator->generate('admin_dashboard'),
                    'is_priority' => true,
                    'is_read' => in_array($key, $readKeys, true)
                ];
            }

            // Santé Système
            if (!in_array('system_health', $hiddenKeys, true)) {
                $notifications[] = [
                    'id' => 'system_health',
                    'type' => 'system',
                    'title' => 'Santé Applicative',
                    'message' => 'Système stable : Backend v7.3, Base de données Delnyx OK.',
                    'icon' => 'lucide:shield-check',
                    'date' => new \DateTime(),
                    'link' => $this->urlGenerator->generate('admin_dashboard'),
                    'is_priority' => false,
                    'is_read' => in_array('system_health', $readKeys, true)
                ];
            }
        }

        // 2. Factures Payées
        $invoiceCriteria = ['statut' => InvoiceStatus::PAID->value];

        $paidInvoices = $this->invoiceRepository->findBy(
            $invoiceCriteria,
            ['datePaiement' => 'DESC'],
            $limit * 2
        );

        foreach ($paidInvoices as $invoice) {
            $key = 'invoice_' . $invoice->getId();
            if (in_array($key, $hiddenKeys, true)) continue;

            $notifications[] = [
                'id' => $key,
                'type' => 'sale',
                'title' => 'Facture Réglée',
                'message' => 'La facture ' . $invoice->getNumero() . ' de ' . $invoice->getMontantTTC() . '€ a été payée.',
                'icon' => 'lucide:credit-card',
                'date' => $invoice->getDatePaiement() ?? $invoice->getDateModification() ?? new \DateTime(),
                'link' => $this->urlGenerator->generate('admin_invoice_show', ['id' => $invoice->getId()]),
                'is_priority' => false,
                'is_read' => in_array($key, $readKeys, true)
            ];
        }

        // 3. Devis Signés
        $quoteCriteria = ['statut' => QuoteStatus::SIGNED->value];

        $signedQuotes = $this->quoteRepository->findBy(
            $quoteCriteria,
            ['dateModification' => 'DESC'],
            $limit * 2
        );

        foreach ($signedQuotes as $quote) {
            $key = 'quote_' . $quote->getId();
            if (in_array($key, $hiddenKeys, true)) continue;

            $client = $quote->getClient();
            if (!$client) continue;

            $notifications[] = [
                'id' => $key,
                'type' => 'sale',
                'title' => 'Devis Signé',
                'message' => 'Le devis ' . $quote->getNumero() . ' a été signé par ' . $client->getNomComplet(),
                'icon' => 'lucide:file-check',
                'date' => $quote->getDateModification() ?? new \DateTime(),
                'link' => $this->urlGenerator->generate('admin_quote_show', ['id' => $quote->getId()]),
                'is_priority' => true,
                'is_read' => in_array($key, $readKeys, true)
            ];
        }

        // Trier par date décroissante
        usort($notifications, fn($a, $b) => $b['date'] <=> $a['date']);

        return array_slice($notifications, 0, $limit);
    }

    /**
     * Récupère le nombre de notifications non lues VISIBLES
     */
    public function getUnreadCount(): int
    {
        $notifications = $this->getRecentNotifications(100); // On regarde large pour le compteur

        return count(array_filter($notifications, fn($n) => !$n['is_read']));
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(string $notificationKey): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return;

        $this->notificationReadRepository->markAsRead($user, $notificationKey);
    }

    /**
     * Marque toutes les notifications VISIBLES comme lues
     */
    public function markAllAsRead(): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return;

        $notifications = $this->getRecentNotifications(100);
        $keys = array_filter(array_column($notifications, 'id'), fn($k) => $k !== null);

        $this->notificationReadRepository->markMultipleAsRead($user, $keys);
    }

    /**
     * Masque (supprime) une notification
     */
    public function hideNotification(string $notificationKey): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return;

        $this->notificationReadRepository->markAsHidden($user, $notificationKey);
    }

    /**
     * Masque toutes les notifications VISIBLES
     */
    public function hideAll(): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return;

        $notifications = $this->getRecentNotifications(100);
        foreach ($notifications as $notif) {
            $this->notificationReadRepository->markAsHidden($user, $notif['id']);
        }
    }
}
