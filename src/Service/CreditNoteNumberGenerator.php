<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CreditNote;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/**
 * Service pour générer les numéros d'avoir de manière sécurisée
 * 
 * Utilise un verrou pessimiste pour éviter les doublons en cas d'accès concurrent
 * Format : CN-YYYYMM-XXX (ex: CN-202501-001)
 * 
 * Conformité légale française : numérotation séquentielle continue sans rupture
 * 
 * @package App\Service
 */
class CreditNoteNumberGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Génère un numéro d'avoir unique pour l'année/mois en cours
     * Utilise un verrou pessimiste pour garantir l'unicité
     * 
     * @param CreditNote $creditNote L'avoir pour lequel générer le numéro
     * @return string Le numéro généré (ex: CN-202501-001)
     * @throws \RuntimeException en cas d'erreur
     */
    public function generate(CreditNote $creditNote): string
    {
        // Si un numéro existe déjà, le retourner
        if ($creditNote->getNumber() !== null) {
            return $creditNote->getNumber();
        }

        $yearMonth = date('Ym'); // Format YYYYMM
        $connection = $this->entityManager->getConnection();

        // Démarrer une transaction pour le verrou
        $connection->beginTransaction();

        try {
            // Trouver le dernier numéro pour ce mois avec un verrou pessimiste
            $lastCreditNote = $this->entityManager->getRepository(CreditNote::class)
                ->createQueryBuilder('cn')
                ->where('cn.number LIKE :pattern')
                ->setParameter('pattern', sprintf('CN-%s-%%', $yearMonth))
                ->orderBy('cn.number', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            $sequence = 1;
            if ($lastCreditNote && $lastCreditNote->getNumber()) {
                // Extraire le numéro de séquence du dernier avoir
                // Format attendu : CN-YYYYMM-XXX
                $parts = explode('-', $lastCreditNote->getNumber());
                if (count($parts) === 3 && is_numeric($parts[2])) {
                    $sequence = (int) $parts[2] + 1;
                }
            }

            // Générer le numéro au format CN-YYYYMM-XXX (ex: CN-202501-001)
            $numero = sprintf('CN-%s-%03d', $yearMonth, $sequence);

            // Vérifier l'unicité (double vérification)
            $existing = $this->entityManager->getRepository(CreditNote::class)
                ->findOneBy(['number' => $numero]);

            if ($existing) {
                throw new \RuntimeException(
                    sprintf(
                        'Un conflit de numérotation a été détecté. Le numéro %s existe déjà.',
                        $numero
                    )
                );
            }

            $connection->commit();
            return $numero;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new \RuntimeException(
                sprintf('Erreur lors de la génération du numéro d\'avoir : %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}

