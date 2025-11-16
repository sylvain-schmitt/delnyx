<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/**
 * Service pour générer les numéros de facture de manière sécurisée
 * 
 * Utilise un verrou pessimiste pour éviter les doublons en cas d'accès concurrent
 * Format : FACT-YYYY-XXX (ex: FACT-2025-001)
 * 
 * Conformité légale française : numérotation séquentielle continue sans rupture
 * 
 * @package App\Service
 */
class InvoiceNumberGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Génère un numéro de facture unique pour l'année en cours
     * Utilise un verrou pessimiste pour garantir l'unicité
     * 
     * @param Invoice $invoice La facture pour laquelle générer le numéro
     * @return string Le numéro généré (ex: FACT-2025-001)
     * @throws \RuntimeException en cas d'erreur
     */
    public function generate(Invoice $invoice): string
    {
        // Si un numéro existe déjà, le retourner
        if ($invoice->getNumero() !== null) {
            return $invoice->getNumero();
        }

        $year = (int) date('Y');
        $connection = $this->entityManager->getConnection();

        // Démarrer une transaction pour le verrou
        $connection->beginTransaction();

        try {
            // Trouver le dernier numéro pour cette année avec un verrou pessimiste
            $lastInvoice = $this->entityManager->getRepository(Invoice::class)
                ->createQueryBuilder('i')
                ->where('i.numero LIKE :pattern')
                ->setParameter('pattern', sprintf('FACT-%d-%%', $year))
                ->orderBy('i.numero', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            $sequence = 1;
            if ($lastInvoice && $lastInvoice->getNumero()) {
                // Extraire le numéro de séquence de la dernière facture
                // Format attendu : FACT-YYYY-XXX
                $parts = explode('-', $lastInvoice->getNumero());
                if (count($parts) === 3 && is_numeric($parts[2])) {
                    $sequence = (int) $parts[2] + 1;
                }
            }

            // Générer le numéro au format FACT-YYYY-XXX (ex: FACT-2025-001)
            $numero = sprintf('FACT-%d-%03d', $year, $sequence);

            // Vérifier l'unicité (double vérification)
            $existing = $this->entityManager->getRepository(Invoice::class)
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
                sprintf('Erreur lors de la génération du numéro de facture : %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}

