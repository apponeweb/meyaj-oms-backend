<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\InventoryManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:expire',
    description: 'Expire active reservations that have passed their expiration date',
)]
class ExpireReservationsCommand extends Command
{
    public function __construct(
        private readonly InventoryManager $inventoryManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->inventoryManager->expireReservations();

        $io->success(sprintf('Se expiraron %d reservaciones.', $count));

        return Command::SUCCESS;
    }
}
