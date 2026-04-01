<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\InventorySnapshotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:snapshot',
    description: 'Genera snapshots diarios de inventario por bodega',
)]
class GenerateInventorySnapshotCommand extends Command
{
    public function __construct(
        private readonly InventorySnapshotService $snapshotService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', 'd', InputOption::VALUE_OPTIONAL, 'Fecha del snapshot (Y-m-d). Default: hoy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateStr = $input->getOption('date');
        $date = $dateStr ? new \DateTimeImmutable($dateStr) : new \DateTimeImmutable();

        $io->info(sprintf('Generando snapshots para %s...', $date->format('Y-m-d')));

        $count = $this->snapshotService->generateSnapshot($date);

        $io->success(sprintf('%d snapshots generados exitosamente.', $count));

        return Command::SUCCESS;
    }
}
