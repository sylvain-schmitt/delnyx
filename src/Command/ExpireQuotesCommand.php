<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Service\QuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/**
 * Commande pour expirer automatiquement les devis dont la date de validité est dépassée
 * 
 * Cette commande est exécutée automatiquement via Symfony Scheduler
 * 
 * @package App\Command
 */
#[AsCommand(
    name: 'app:quotes:expire',
    description: 'Marque comme expirés les devis dont la date de validité est dépassée'
)]
#[AsPeriodicTask(
    frequency: '1 hour', // Exécution toutes les heures
    schedule: 'default'
)]
class ExpireQuotesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuoteService $quoteService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Expiration automatique des devis');

        // Récupérer tous les devis qui ne sont pas déjà dans un état final
        // et dont la date de validité est dépassée
        $qb = $this->entityManager->getRepository(Quote::class)->createQueryBuilder('q');
        
        $qb->where('q.dateValidite IS NOT NULL')
            ->andWhere('q.dateValidite < :now')
            ->andWhere('q.statut NOT IN (:finalStatuses)')
            ->setParameter('now', new \DateTime())
            ->setParameter('finalStatuses', [
                QuoteStatus::SIGNED,
                QuoteStatus::REFUSED,
                QuoteStatus::EXPIRED,
                QuoteStatus::CANCELLED,
            ]);

        $quotes = $qb->getQuery()->getResult();

        if (empty($quotes)) {
            $io->success('Aucun devis à expirer.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Traitement de %d devis à expirer...', count($quotes)));

        $expiredCount = 0;
        foreach ($quotes as $quote) {
            try {
                // Utiliser le service pour expirer le devis (avec audit)
                $this->quoteService->expireIfNeeded($quote);
                $expiredCount++;
            } catch (\Exception $e) {
                $io->error(sprintf(
                    'Erreur lors de l\'expiration du devis #%s : %s',
                    $quote->getNumero() ?? $quote->getId(),
                    $e->getMessage()
                ));
            }
        }

        $this->entityManager->flush();

        if ($expiredCount > 0) {
            $io->success(sprintf('%d devis expiré(s) avec succès.', $expiredCount));
        }

        return Command::SUCCESS;
    }

}

