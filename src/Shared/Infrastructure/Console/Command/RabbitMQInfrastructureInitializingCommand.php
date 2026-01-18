<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console\Command;

use App\Shared\Infrastructure\Messaging\RabbitMQ\Config\RabbitMQInfrastructureConfig;
use App\Shared\Infrastructure\Messaging\RabbitMQ\RabbitMQInfrastructureInitializingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rabbitmq:infrastructure_init',
    description: 'Initialize queues infrastructure for RabbitMQ.',
    help: 'This command initialize queues infrastructure for RabbitMQ.',

)]
final class RabbitMQInfrastructureInitializingCommand extends Command
{
    public function __construct(
        private readonly RabbitMQInfrastructureInitializingService $rabbitMQInfrastructureInitializingService,
        private readonly RabbitMQInfrastructureConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'dry-run',
            mode: InputOption::VALUE_NONE,
            description: 'Show what would be created without actually creating it',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('RabbitMQ Infrastructure Setup');

        $dryRun = $input->getOption('dry-run');
        if ($dryRun) {
            $this->showDryRun($io, $this->config);

            return Command::SUCCESS;
        }

        try {
            $io->section('Initializing RabbitMQ infrastructure...');

            $this->rabbitMQInfrastructureInitializingService->initialize();

            $io->success([
                'RabbitMQ infrastructure initialized successfully!',
                sprintf('Exchanges: %d', count($this->config->exchanges)),
                sprintf('Queues: %d', count($this->config->queues)),
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error([
                'Failed to initialize RabbitMQ infrastructure.',
                $exception->getMessage()
            ]);

            return Command::FAILURE;
        }
    }

    private function showDryRun(SymfonyStyle $io, RabbitMqInfrastructureConfig $config): void
    {
        $io->section('Exchanges to be created:');
        $exchangesTable = [];
        foreach ($config->exchanges as $exchange) {
            $exchangesTable[] = [
                $exchange->name,
                $exchange->type,
                $exchange->durable ? 'Yes' : 'No',
            ];
        }
        $io->table(['Name', 'Type', 'Durable'], $exchangesTable);

        $io->section('Queues to be created:');
        $queuesTable = [];
        foreach ($config->queues as $queue) {
            $bindings = implode(', ', array_map(
                fn($b) => "{$b->exchange}:{$b->routingKey}",
                $queue->bindings
            ));

            $queuesTable[] = [
                $queue->name,
                $queue->durable ? 'Yes' : 'No',
                count($queue->bindings),
                $bindings,
            ];
        }
        $io->table(['Name', 'Durable', 'Bindings', 'Binding Details'], $queuesTable);

        $io->note('This is a dry run. No changes were made to RabbitMQ.');
    }
}
