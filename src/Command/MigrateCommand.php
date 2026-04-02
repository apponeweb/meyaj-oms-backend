<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate',
    description: 'Run pending schema updates, seed modules, and assign admin permissions in one step',
)]
class MigrateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Schema update
        $io->section('1/3 - Updating database schema...');
        $schemaCmd = $this->getApplication()->find('doctrine:schema:update');
        $schemaCmd->run(new ArrayInput(['--force' => true]), $output);

        // 2. Seed modules
        $io->section('2/3 - Seeding modules, functions, and actions...');
        $seedCmd = $this->getApplication()->find('app:seed-modules');
        $seedCmd->run(new ArrayInput([]), $output);

        // 3. Admin permissions
        $io->section('3/3 - Assigning admin permissions...');
        $permCmd = $this->getApplication()->find('app:admin-permissions');
        $permCmd->run(new ArrayInput([]), $output);

        $io->success('Migration complete. Schema updated, modules seeded, and admin permissions assigned.');

        return Command::SUCCESS;
    }
}
