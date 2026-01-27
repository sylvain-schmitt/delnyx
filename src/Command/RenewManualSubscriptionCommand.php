<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Subscription;
use App\Message\RenewManualSubscriptionMessage;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:subscription:renew-manual',
    description: 'Vérifie et renouvelle les abonnements manuels arrivés à échéance',
)]
class RenewManualSubscriptionCommand extends Command
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule le renouvellement sans rien créer')
            ->addOption('days-before', null, InputOption::VALUE_OPTIONAL, 'Nombre de jours avant échéance pour renouveler', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $daysBefore = (int) $input->getOption('days-before');

        $io->title('Renouvellement des abonnements manuels');

        // Date cible : Aujourd'hui + marge jours (ex: générer la facture 0 jour avant la fin)
        $targetDate = (new \DateTime())->modify(sprintf('+%d days', $daysBefore));
        // On veut le début de journée pour inclure tout ce qui expire aujourd'hui
        $targetDate->setTime(23, 59, 59);

        // Récupérer les abonnements actifs, manuels (pas de stripe ID), et dont la fin de période est <= targetDate
        // On doit faire une requête custom car findBy ne suffit pas pour les dates <
        $qb = $this->subscriptionRepository->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.stripeSubscriptionId IS NULL')
            ->andWhere('s.currentPeriodEnd <= :targetDate')
            ->setParameter('status', 'active')
            ->setParameter('targetDate', $targetDate);

        $subscriptions = $qb->getQuery()->getResult();

        $count = count($subscriptions);
        $io->text(sprintf('Trouvé %d abonnement(s) à renouveler (échéance <= %s).', $count, $targetDate->format('d/m/Y')));

        if ($count === 0) {
            $io->success('Aucun abonnement à renouveler.');
            return Command::SUCCESS;
        }

        foreach ($subscriptions as $subscription) {
            $io->section(sprintf('Abonnement #%d - %s (%s)', $subscription->getId(), $subscription->getClient()->getNomComplet(), $subscription->getLabel()));
            $io->text(sprintf('Fin de période : %s', $subscription->getCurrentPeriodEnd()->format('d/m/Y')));

            if ($dryRun) {
                $io->note('Mode Dry-Run : Pas de message envoyé.');
                continue;
            }

            // Envoi du message asynchrone
            try {
                $this->messageBus->dispatch(new RenewManualSubscriptionMessage($subscription->getId()));
                $io->success('Message de renouvellement envoyé.');
            } catch (\Exception $e) {
                $io->error('Erreur lors de l\'envoi du message : ' . $e->getMessage());
            }
        }

        $io->success('Terminé.');

        return Command::SUCCESS;
    }
}
