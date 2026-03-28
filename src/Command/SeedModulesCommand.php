<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ActionCatalog;
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
    name: 'app:seed-modules',
    description: 'Seed modules, actions, and default admin role with full permissions',
)]
class SeedModulesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Seed modules
        $modulesData = [
            ['code' => 'catalogos', 'name' => 'Catálogos', 'icon' => 'Building2', 'order' => 1],
            ['code' => 'seguridad', 'name' => 'Seguridad', 'icon' => 'Shield', 'order' => 2],
            ['code' => 'proveedor', 'name' => 'Proveedores', 'icon' => 'Truck', 'order' => 3],
            ['code' => 'productos', 'name' => 'Productos', 'icon' => 'Package', 'order' => 4],
            ['code' => 'ventas', 'name' => 'Ventas', 'icon' => 'ShoppingCart', 'order' => 5],
            ['code' => 'reportes', 'name' => 'Reportes', 'icon' => 'BarChart3', 'order' => 6],
        ];

        $moduleRepo = $this->em->getRepository(AppModule::class);
        $modules = [];

        foreach ($modulesData as $data) {
            $existing = $moduleRepo->findOneBy(['code' => $data['code']]);
            if ($existing) {
                $modules[$data['code']] = $existing;
                $io->note("Module '{$data['code']}' already exists, skipping.");
                continue;
            }

            $module = new AppModule();
            $module->setCode($data['code']);
            $module->setName($data['name']);
            $module->setIcon($data['icon']);
            $module->setDisplayOrder($data['order']);
            $module->setActive(true);
            $this->em->persist($module);
            $modules[$data['code']] = $module;
            $io->info("Created module: {$data['name']}");
        }

        // Seed actions
        $actionsData = [
            ['code' => 'create', 'name' => 'Crear'],
            ['code' => 'read', 'name' => 'Leer'],
            ['code' => 'update', 'name' => 'Actualizar'],
            ['code' => 'delete', 'name' => 'Eliminar'],
            ['code' => 'export', 'name' => 'Exportar'],
        ];

        $actionRepo = $this->em->getRepository(ActionCatalog::class);
        $actions = [];

        foreach ($actionsData as $data) {
            $existing = $actionRepo->findOneBy(['code' => $data['code']]);
            if ($existing) {
                $actions[$data['code']] = $existing;
                continue;
            }

            $action = new ActionCatalog();
            $action->setCode($data['code']);
            $action->setName($data['name']);
            $this->em->persist($action);
            $actions[$data['code']] = $action;
            $io->info("Created action: {$data['name']}");
        }

        // Seed default admin role
        $roleRepo = $this->em->getRepository(Role::class);
        $adminRole = $roleRepo->findOneBy(['name' => 'Administrador']);

        if (!$adminRole) {
            $adminRole = new Role();
            $adminRole->setName('Administrador');
            $adminRole->setDescription('Acceso total a todos los módulos del sistema');
            $adminRole->setActive(true);
            $this->em->persist($adminRole);
            $io->info('Created role: Administrador');
        }

        // Flush to get IDs
        $this->em->flush();

        // Create module permissions and action permissions for admin role
        $rmpRepo = $this->em->getRepository(RoleModulePermission::class);
        $rapRepo = $this->em->getRepository(RoleActionPermission::class);

        foreach ($modules as $module) {
            // Module access
            $existingRmp = $rmpRepo->findOneBy(['role' => $adminRole, 'appModule' => $module]);
            if (!$existingRmp) {
                $rmp = new RoleModulePermission();
                $rmp->setRole($adminRole);
                $rmp->setAppModule($module);
                $rmp->setCanAccess(true);
                $this->em->persist($rmp);
            }

            // Action permissions
            foreach ($actions as $action) {
                $existingRap = $rapRepo->findOneBy([
                    'role' => $adminRole,
                    'appModule' => $module,
                    'action' => $action,
                ]);
                if (!$existingRap) {
                    $rap = new RoleActionPermission();
                    $rap->setRole($adminRole);
                    $rap->setAppModule($module);
                    $rap->setAction($action);
                    $rap->setAllowed(true);
                    $this->em->persist($rap);
                }
            }
        }

        // Also create a "Vendedor" role with limited access
        $vendedorRole = $roleRepo->findOneBy(['name' => 'Vendedor']);
        if (!$vendedorRole) {
            $vendedorRole = new Role();
            $vendedorRole->setName('Vendedor');
            $vendedorRole->setDescription('Acceso a ventas y reportes');
            $vendedorRole->setActive(true);
            $this->em->persist($vendedorRole);
            $io->info('Created role: Vendedor');

            $this->em->flush();

            // Vendedor: access to ventas and reportes only
            foreach (['ventas', 'reportes'] as $code) {
                $module = $modules[$code];

                $rmp = new RoleModulePermission();
                $rmp->setRole($vendedorRole);
                $rmp->setAppModule($module);
                $rmp->setCanAccess(true);
                $this->em->persist($rmp);

                $vendedorActions = $code === 'ventas'
                    ? ['create', 'read', 'update']
                    : ['read', 'export'];

                foreach ($vendedorActions as $actionCode) {
                    $rap = new RoleActionPermission();
                    $rap->setRole($vendedorRole);
                    $rap->setAppModule($module);
                    $rap->setAction($actions[$actionCode]);
                    $rap->setAllowed(true);
                    $this->em->persist($rap);
                }
            }
        }

        $this->em->flush();

        $io->success('Modules, actions, and roles seeded successfully.');

        return Command::SUCCESS;
    }
}
