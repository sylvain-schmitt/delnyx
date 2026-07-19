<?php

namespace App\Command;

use App\Message\TransmitInvoiceToPAMessage;
use App\Repository\InvoiceRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:transmit-invoice', description: 'Dispatch TransmitInvoiceToPAMessage for an invoice')]
class TestTransmitCommand extends Command
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('numero', InputArgument::REQUIRED, 'Invoice number (e.g. FACT-2026-004)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $numero = $input->getArgument('numero');
        $invoice = $this->invoiceRepository->findOneBy(['numero' => $numero]);

        if (!$invoice) {
            $output->writeln("<error>Invoice {$numero} not found</error>");
            return Command::FAILURE;
        }

        $output->writeln("Dispatching TransmitInvoiceToPAMessage for invoice {$invoice->getId()} ({$numero})");
        $this->bus->dispatch(new TransmitInvoiceToPAMessage($invoice->getId()));
        $output->writeln('Message dispatched.');

        return Command::SUCCESS;
    }
}
