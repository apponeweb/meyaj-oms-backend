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
    name: 'app:seed-modules',
    description: 'Seed modules, functions, actions, and default roles with permissions',
)]
class SeedModulesCommand extends Command
{
    private const READ_ONLY_FUNCTIONS = ['modules_mgmt', 'functions_mgmt', 'actions_mgmt'];

    private const MODULES = [
        ['code' => 'catalogos', 'name' => 'Catálogos', 'icon' => 'Building2', 'order' => 1],
        ['code' => 'seguridad', 'name' => 'Seguridad', 'icon' => 'Shield', 'order' => 2],
        ['code' => 'proveedor', 'name' => 'Proveedores', 'icon' => 'Truck', 'order' => 3],
        ['code' => 'productos', 'name' => 'Productos', 'icon' => 'Package', 'order' => 4],
        ['code' => 'ventas', 'name' => 'Ventas', 'icon' => 'ShoppingCart', 'order' => 5],
        ['code' => 'almacenes', 'name' => 'Almacenes', 'icon' => 'Warehouse', 'order' => 6],
        ['code' => 'inventario', 'name' => 'Inventario', 'icon' => 'ClipboardList', 'order' => 7],
        ['code' => 'compras', 'name' => 'Compras', 'icon' => 'ShoppingBag', 'order' => 8],
        ['code' => 'pedidos', 'name' => 'Pedidos', 'icon' => 'FileText', 'order' => 9],
        ['code' => 'reportes', 'name' => 'Reportes', 'icon' => 'BarChart3', 'order' => 10],
    ];

    private const FUNCTIONS = [
        // Catálogos
        ['module' => 'catalogos', 'code' => 'companies', 'name' => 'Empresas', 'order' => 1],
        ['module' => 'catalogos', 'code' => 'branches', 'name' => 'Sucursales', 'order' => 2],
        ['module' => 'catalogos', 'code' => 'departments', 'name' => 'Departamentos', 'order' => 3],
        // Seguridad - Control de acceso
        ['module' => 'seguridad', 'code' => 'roles', 'name' => 'Roles', 'order' => 1],
        ['module' => 'seguridad', 'code' => 'permissions', 'name' => 'Permisos', 'order' => 2],
        ['module' => 'seguridad', 'code' => 'users', 'name' => 'Usuarios', 'order' => 3],
        ['module' => 'seguridad', 'code' => 'sessions', 'name' => 'Sesiones', 'order' => 4],
        // Seguridad - Configuración (read-only)
        ['module' => 'seguridad', 'code' => 'modules_mgmt', 'name' => 'Módulos', 'order' => 5],
        ['module' => 'seguridad', 'code' => 'functions_mgmt', 'name' => 'Funcionalidades', 'order' => 6],
        ['module' => 'seguridad', 'code' => 'actions_mgmt', 'name' => 'Acciones', 'order' => 7],
        // Proveedores
        ['module' => 'proveedor', 'code' => 'suppliers', 'name' => 'Proveedores', 'order' => 1],
        ['module' => 'proveedor', 'code' => 'brands', 'name' => 'Marcas', 'order' => 2],
        // Productos - Inventario
        ['module' => 'productos', 'code' => 'pacas', 'name' => 'Pacas', 'order' => 1],
        // Productos - Catálogos
        ['module' => 'productos', 'code' => 'labels', 'name' => 'Etiquetas', 'order' => 2],
        ['module' => 'productos', 'code' => 'qualities', 'name' => 'Calidades', 'order' => 3],
        ['module' => 'productos', 'code' => 'seasons', 'name' => 'Temporadas', 'order' => 4],
        ['module' => 'productos', 'code' => 'genders', 'name' => 'Género', 'order' => 5],
        ['module' => 'productos', 'code' => 'garment_types', 'name' => 'Tipos de prenda', 'order' => 6],
        ['module' => 'productos', 'code' => 'fabric_types', 'name' => 'Tipos de tela', 'order' => 7],
        ['module' => 'productos', 'code' => 'size_profiles', 'name' => 'Perfiles de talla', 'order' => 8],
        // Ventas
        ['module' => 'ventas', 'code' => 'pos', 'name' => 'Punto de venta', 'order' => 1],
        ['module' => 'ventas', 'code' => 'sales', 'name' => 'Historial de ventas', 'order' => 2],
        ['module' => 'ventas', 'code' => 'customers', 'name' => 'Clientes', 'order' => 3],
        // Almacenes
        ['module' => 'almacenes', 'code' => 'warehouses', 'name' => 'Bodegas', 'order' => 1],
        ['module' => 'almacenes', 'code' => 'warehouse_bins', 'name' => 'Ubicaciones', 'order' => 2],
        ['module' => 'almacenes', 'code' => 'warehouse_types', 'name' => 'Tipos de Bodega', 'order' => 3],
        // Inventario
        ['module' => 'inventario', 'code' => 'kardex', 'name' => 'Kardex', 'order' => 1],
        ['module' => 'inventario', 'code' => 'inventory_counts', 'name' => 'Conteos Físicos', 'order' => 2],
        ['module' => 'inventario', 'code' => 'inventory_dashboard', 'name' => 'Dashboard Almacén', 'order' => 3],
        ['module' => 'inventario', 'code' => 'inventory_reasons', 'name' => 'Motivos de Movimiento', 'order' => 4],
        ['module' => 'inventario', 'code' => 'inventory_reservations', 'name' => 'Reservas de Inventario', 'order' => 5],
        // Compras
        ['module' => 'compras', 'code' => 'purchase_orders', 'name' => 'Órdenes de Compra', 'order' => 1],
        // Pedidos
        ['module' => 'pedidos', 'code' => 'sales_orders', 'name' => 'Pedidos de Venta', 'order' => 1],
        ['module' => 'pedidos', 'code' => 'order_tracking', 'name' => 'Seguimiento', 'order' => 2],
        // Reportes
        ['module' => 'reportes', 'code' => 'dashboard', 'name' => 'Dashboard', 'order' => 1],
        ['module' => 'reportes', 'code' => 'daily_reports', 'name' => 'Reportes diarios', 'order' => 2],
        ['module' => 'reportes', 'code' => 'monthly_reports', 'name' => 'Reportes mensuales', 'order' => 3],
    ];

    private const ACTIONS = [
        ['code' => 'create', 'name' => 'Crear'],
        ['code' => 'read', 'name' => 'Leer'],
        ['code' => 'update', 'name' => 'Actualizar'],
        ['code' => 'delete', 'name' => 'Eliminar'],
        ['code' => 'export', 'name' => 'Exportar'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $modules = $this->seedModules($io);
        $actions = $this->seedActions($io);
        $functions = $this->seedFunctions($io, $modules);

        $this->em->flush();

        $this->seedAdminRole($io, $modules, $functions, $actions);
        $this->seedVendedorRole($io, $modules, $functions, $actions);

        $this->em->flush();
        $io->success('Modules, functions, actions, and roles seeded successfully.');

        return Command::SUCCESS;
    }

    private function seedModules(SymfonyStyle $io): array
    {
        $moduleRepo = $this->em->getRepository(AppModule::class);
        $modules = [];

        foreach (self::MODULES as $data) {
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

        return $modules;
    }

    private function seedActions(SymfonyStyle $io): array
    {
        $actionRepo = $this->em->getRepository(ActionCatalog::class);
        $actions = [];

        foreach (self::ACTIONS as $data) {
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

        return $actions;
    }

    private function seedFunctions(SymfonyStyle $io, array $modules): array
    {
        $fnRepo = $this->em->getRepository(AppFunction::class);
        $functions = [];

        foreach (self::FUNCTIONS as $data) {
            $existing = $fnRepo->findOneBy(['code' => $data['code']]);
            if ($existing) {
                $functions[$data['code']] = $existing;
                $io->note("Function '{$data['code']}' already exists, skipping.");
                continue;
            }

            $fn = new AppFunction();
            $fn->setAppModule($modules[$data['module']]);
            $fn->setCode($data['code']);
            $fn->setName($data['name']);
            $fn->setDisplayOrder($data['order']);
            $fn->setActive(true);
            $this->em->persist($fn);
            $functions[$data['code']] = $fn;
            $io->info("Created function: {$data['name']}");
        }

        return $functions;
    }

    private function seedAdminRole(SymfonyStyle $io, array $modules, array $functions, array $actions): void
    {
        $roleRepo = $this->em->getRepository(Role::class);
        $adminRole = $roleRepo->findOneBy(['name' => 'Administrador']);

        if (!$adminRole) {
            $adminRole = new Role();
            $adminRole->setName('Administrador');
            $adminRole->setDescription('Acceso total a todos los módulos del sistema');
            $adminRole->setActive(true);
            $this->em->persist($adminRole);
            $this->em->flush();
            $io->info('Created role: Administrador');
        }

        $rmpRepo = $this->em->getRepository(RoleModulePermission::class);
        $rapRepo = $this->em->getRepository(RoleActionPermission::class);

        // Module access
        foreach ($modules as $module) {
            $existingRmp = $rmpRepo->findOneBy(['role' => $adminRole, 'appModule' => $module]);
            if (!$existingRmp) {
                $rmp = new RoleModulePermission();
                $rmp->setRole($adminRole);
                $rmp->setAppModule($module);
                $rmp->setCanAccess(true);
                $this->em->persist($rmp);
            }
        }

        // Function-level action permissions
        foreach ($functions as $fnCode => $fn) {
            $isReadOnly = in_array($fnCode, self::READ_ONLY_FUNCTIONS, true);
            $allowedActions = $isReadOnly ? ['read'] : array_keys($actions);

            foreach ($allowedActions as $actionCode) {
                $action = $actions[$actionCode];
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
                }
            }
        }
    }

    private function seedVendedorRole(SymfonyStyle $io, array $modules, array $functions, array $actions): void
    {
        $roleRepo = $this->em->getRepository(Role::class);
        $vendedorRole = $roleRepo->findOneBy(['name' => 'Vendedor']);

        if ($vendedorRole) {
            return;
        }

        $vendedorRole = new Role();
        $vendedorRole->setName('Vendedor');
        $vendedorRole->setDescription('Acceso a ventas y reportes');
        $vendedorRole->setActive(true);
        $this->em->persist($vendedorRole);
        $this->em->flush();
        $io->info('Created role: Vendedor');

        // Module access: ventas and reportes
        foreach (['ventas', 'reportes'] as $code) {
            $module = $modules[$code];

            $rmp = new RoleModulePermission();
            $rmp->setRole($vendedorRole);
            $rmp->setAppModule($module);
            $rmp->setCanAccess(true);
            $this->em->persist($rmp);
        }

        // Vendedor function permissions
        $vendedorFunctionActions = [
            'pos' => ['create', 'read', 'update'],
            'sales' => ['read'],
            'customers' => ['create', 'read', 'update'],
            'dashboard' => ['read'],
            'daily_reports' => ['read', 'export'],
            'monthly_reports' => ['read', 'export'],
        ];

        foreach ($vendedorFunctionActions as $fnCode => $allowedActions) {
            if (!isset($functions[$fnCode])) continue;
            $fn = $functions[$fnCode];

            foreach ($allowedActions as $actionCode) {
                if (!isset($actions[$actionCode])) continue;
                $rap = new RoleActionPermission();
                $rap->setRole($vendedorRole);
                $rap->setAppFunction($fn);
                $rap->setAction($actions[$actionCode]);
                $rap->setAllowed(true);
                $this->em->persist($rap);
            }
        }
    }
}
