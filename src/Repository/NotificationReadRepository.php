<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationRead;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationRead>
 */
class NotificationReadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationRead::class);
    }

    /**
     * Vérifie si une notification a été lue par un utilisateur (et n'est pas cachée)
     */
    public function isRead(User $user, string $notificationKey): bool
    {
        $read = $this->findOneBy([
            'user' => $user,
            'notificationKey' => $notificationKey
        ]);

        return $read !== null && !$read->isHidden();
    }

    /**
     * Récupère toutes les clés de notifications lues par un utilisateur
     * @return array<string>
     */
    public function getReadKeys(User $user): array
    {
        $results = $this->createQueryBuilder('nr')
            ->select('nr.notificationKey')
            ->where('nr.user = :user')
            ->andWhere('nr.isHidden = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'notificationKey');
    }

    /**
     * Récupère toutes les clés de notifications cachées par un utilisateur
     * @return array<string>
     */
    public function getHiddenKeys(User $user): array
    {
        $results = $this->createQueryBuilder('nr')
            ->select('nr.notificationKey')
            ->where('nr.user = :user')
            ->andWhere('nr.isHidden = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'notificationKey');
    }

    /**
     * Marque une notification comme cachée
     */
    public function markAsHidden(User $user, string $notificationKey): void
    {
        $read = $this->findOneBy([
            'user' => $user,
            'notificationKey' => $notificationKey
        ]);

        if (!$read) {
            $read = new NotificationRead();
            $read->setUser($user);
            $read->setNotificationKey($notificationKey);
        }

        $read->setIsHidden(true);
        $read->setReadAt(new \DateTime()); // On considère que si c'est caché, c'est lu

        $this->getEntityManager()->persist($read);
        $this->getEntityManager()->flush();
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(User $user, string $notificationKey): void
    {
        if ($this->isRead($user, $notificationKey)) {
            return;
        }

        $read = new NotificationRead();
        $read->setUser($user);
        $read->setNotificationKey($notificationKey);

        $this->getEntityManager()->persist($read);
        $this->getEntityManager()->flush();
    }

    /**
     * Marque plusieurs notifications comme lues
     * @param array<string> $keys
     */
    public function markMultipleAsRead(User $user, array $keys): void
    {
        $existingKeys = $this->getReadKeys($user);
        $newKeys = array_diff($keys, $existingKeys);

        foreach ($newKeys as $key) {
            $read = new NotificationRead();
            $read->setUser($user);
            $read->setNotificationKey($key);
            $this->getEntityManager()->persist($read);
        }

        if (!empty($newKeys)) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime les entrées de lecture plus anciennes que X jours (nettoyage)
     */
    public function cleanOldEntries(int $days = 30): int
    {
        $date = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('nr')
            ->delete()
            ->where('nr.readAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
