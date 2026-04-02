<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ActionCatalog;
use App\Entity\AppFunction;
use App\Entity\AppModule;
use App\Entity\Role;
use App\Entity\RoleActionPermission;
use App\Entity\RoleModulePermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:admin-permissions',
    description: 'Assign all permissions (modules + functions + actions) to the Administrador role',
)]
class AssignAdminPermissionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adminRole = $this->em->getRepository(Role::class)->findOneBy(['name' => 'Administrador']);
        if (!$adminRole) {
            $io->error('Role "Administrador" not found. Run app:seed-modules first.');
            return Command::FAILURE;
        }

        $modules = $this->em->getRepository(AppModule::class)->findAll();
        $functions = $this->em->getRepository(AppFunction::class)->findAll();
        $actions = $this->em->getRepository(ActionCatalog::class)->findAll();

        $rmpRepo = $this->em->getRepository(RoleModulePermission::class);
        $rapRepo = $this->em->getRepository(RoleActionPermission::class);

        $modulesAdded = 0;
        $permissionsAdded = 0;

        // Grant access to all modules
        foreach ($modules as $module) {
            $existing = $rmpRepo->findOneBy(['role' => $adminRole, 'appModule' => $module]);
            if (!$existing) {
                $rmp = new RoleModulePermission();
                $rmp->setRole($adminRole);
                $rmp->setAppModule($module);
                $rmp->setCanAccess(true);
                $this->em->persist($rmp);
                $modulesAdded++;
            }
        }

        // Grant all actions on all functions
        foreach ($functions as $fn) {
            foreach ($actions as $action) {
                $existing = $rapRepo->findOneBy([
                    'role' => $adminRole,
                    'appFunction' => $fn,
                    'action' => $action,
                ]);
                if (!$existing) {
                    $rap = new RoleActionPermission();
                    $rap->setRole($adminRole);
                    $rap->setAppFunction($fn);
                    $rap->setAction($action);
                    $rap->setAllowed(true);
                    $this->em->persist($rap);
                    $permissionsAdded++;
                }
            }
        }

        $this->em->flush();

        $io->success(\sprintf(
            'Done. Modules granted: %d, Permissions added: %d (across %d functions × %d actions)',
            $modulesAdded,
            $permissionsAdded,
            \count($functions),
            \count($actions),
        ));

        return Command::SUCCESS;
    }
}
