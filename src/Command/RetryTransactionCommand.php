<?php

declare(strict_types=1);

namespace App\Command;

use App\Banking\Message\ProcessDepositMessage;
use App\Banking\Repository\TransactionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:transaction:retry',
    description: 'Retry processing a pending transaction',
)]
class RetryTransactionCommand extends Command
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('transactionId', InputArgument::REQUIRED, 'Transaction ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $transactionId = $input->getArgument('transactionId');

        $transaction = $this->transactionRepository->find(Uuid::fromString($transactionId));

        if (!$transaction) {
            $io->error('Transaction not found');
            return Command::FAILURE;
        }

        $io->info('Transaction details:');
        $io->table(
            ['Field', 'Value'],
            [
                ['ID', $transaction->getId()->toRfc4122()],
                ['Type', $transaction->getType()->value],
                ['Status', $transaction->getStatus()->value],
                ['Amount', $transaction->getAmountMinorUnits() / 100],
                ['Created', $transaction->getCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );

        if ($transaction->getStatus()->value !== 'pending') {
            $io->warning('Transaction is not in pending status');
            return Command::INVALID;
        }

        $io->info('Dispatching message to retry transaction processing...');

        $message = new ProcessDepositMessage(
            transactionId: $transaction->getId()->toRfc4122(),
            idempotencyKey: 'retry-' . time()
        );

        $this->messageBus->dispatch($message);

        $io->success('Message dispatched. Check worker logs for processing status.');

        return Command::SUCCESS;
    }
}
