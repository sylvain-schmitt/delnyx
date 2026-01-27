<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Quote;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/**
 * Service pour générer les numéros de devis de manière sécurisée
 */
class QuoteNumberGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function generate(Quote $quote): string
    {
        if ($quote->getNumero() !== null) {
            return $quote->getNumero();
        }

        $year = (int) date('Y');
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $lastQuote = $this->entityManager->getRepository(Quote::class)
                ->createQueryBuilder('q')
                ->where('q.numero LIKE :pattern')
                ->setParameter('pattern', sprintf('DEV-%d-%%', $year))
                ->orderBy('q.numero', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();

            $sequence = 1;
            if ($lastQuote && $lastQuote->getNumero()) {
                $parts = explode('-', $lastQuote->getNumero());
                if (count($parts) === 3 && is_numeric($parts[2])) {
                    $sequence = (int) $parts[2] + 1;
                }
            }

            $numero = sprintf('DEV-%d-%03d', $year, $sequence);

            $existing = $this->entityManager->getRepository(Quote::class)
                ->findOneBy(['numero' => $numero]);

            if ($existing) {
                throw new \RuntimeException(sprintf('Numéro %s existe déjà.', $numero));
            }

            $connection->commit();
            return $numero;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new \RuntimeException(sprintf('Erreur génération numéro devis : %s', $e->getMessage()), 0, $e);
        }
    }
}
