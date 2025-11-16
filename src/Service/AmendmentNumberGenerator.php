<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Amendment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/**
 * Service pour générer les numéros d'avenant de manière sécurisée
 * 
 * Utilise un verrou pessimiste pour éviter les doublons en cas d'accès concurrent
 * Format : AMD-YYYYMM-XXX (ex: AMD-202501-001)
 * 
 * Conformité légale française : numérotation séquentielle continue sans rupture
 * 
 * @package App\Service
 */
class AmendmentNumberGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Génère un numéro d'avenant unique pour l'année/mois en cours
     * Utilise un verrou pessimiste pour garantir l'unicité
     * 
     * @param Amendment $amendment L'avenant pour lequel générer le numéro
     * @return string Le numéro généré (ex: AMD-202501-001)
     * @throws \RuntimeException en cas d'erreur
     */
    public function generate(Amendment $amendment): string
    {
        // Si un numéro existe déjà, le retourner
        if ($amendment->getNumero() !== null) {
            return $amendment->getNumero();
        }

        $yearMonth = date('Ym'); // Format YYYYMM
        $connection = $this->entityManager->getConnection();

        // Démarrer une transaction pour le verrou
        $connection->beginTransaction();

        try {
            // Trouver le dernier numéro pour ce mois avec un verrou pessimiste
            $lastAmendment = $this->entityManager->getRepository(Amendment::class)
                ->createQueryBuilder('a')
                ->where('a.numero LIKE :pattern')
                ->setParameter('pattern', sprintf('AMD-%s-%%', $yearMonth))
                ->orderBy('a.numero', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            $sequence = 1;
            if ($lastAmendment && $lastAmendment->getNumero()) {
                // Extraire le numéro de séquence du dernier avenant
                // Format attendu : AMD-YYYYMM-XXX
                $parts = explode('-', $lastAmendment->getNumero());
                if (count($parts) === 3 && is_numeric($parts[2])) {
                    $sequence = (int) $parts[2] + 1;
                }
            }

            // Générer le numéro au format AMD-YYYYMM-XXX (ex: AMD-202501-001)
            $numero = sprintf('AMD-%s-%03d', $yearMonth, $sequence);

            // Vérifier l'unicité (double vérification)
            $existing = $this->entityManager->getRepository(Amendment::class)
                ->findOneBy(['numero' => $numero]);

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
                sprintf('Erreur lors de la génération du numéro d\'avenant : %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}

