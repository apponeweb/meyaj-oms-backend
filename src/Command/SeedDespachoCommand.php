<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-despacho',
    description: 'Seed del modulo Despacho: modulo, funciones y permisos para Administrador',
)]
class SeedDespachoCommand extends Command
{
    public function __construct(private readonly Connection $conn)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Seed: Modulo de Despacho');

        // 1. Modulo
        $exists = $this->conn->fetchOne("SELECT COUNT(*) FROM app_module WHERE code = 'despacho'");
        if ((int) $exists === 0) {
            $this->conn->executeStatement(
                "INSERT INTO app_module (code, name, icon, display_order, active, created_at, updated_at) VALUES ('despacho', 'Despacho', 'Truck', 11, 1, NOW(), NOW())"
            );
            $io->info('Modulo "despacho" creado.');
        } else {
            $io->info('Modulo "despacho" ya existe.');
        }

        // 2. Funciones
        $functions = [
            ['shipments', 'Envios', 'despacho', 1],
            ['dispatch_dashboard', 'Dashboard Despacho', 'despacho', 2],
            ['paca_units', 'Unidades de Paca', 'inventario', 5],
        ];

        foreach ($functions as [$code, $name, $moduleCode, $order]) {
            $exists = $this->conn->fetchOne("SELECT COUNT(*) FROM app_functionality WHERE code = ?", [$code]);
            if ((int) $exists === 0) {
                $moduleId = $this->conn->fetchOne("SELECT id FROM app_module WHERE code = ?", [$moduleCode]);
                if ($moduleId) {
                    $this->conn->executeStatement(
                        "INSERT INTO app_functionality (code, name, app_module_id, display_order, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())",
                        [$code, $name, $moduleId, $order]
                    );
                    $io->info("Funcion \"$code\" creada en modulo \"$moduleCode\".");
                }
            } else {
                $io->info("Funcion \"$code\" ya existe.");
            }
        }

        // 3. Permisos para Administrador
        $adminId = $this->conn->fetchOne("SELECT id FROM app_role WHERE name = 'Administrador'");
        if (!$adminId) {
            $io->warning('No se encontro el rol "Administrador". Asigna permisos manualmente.');
            return Command::SUCCESS;
        }

        // Modulo access
        $exists = $this->conn->fetchOne(
            "SELECT COUNT(*) FROM app_role_module_permission WHERE role_id = ? AND app_module_id = (SELECT id FROM app_module WHERE code = 'despacho')",
            [$adminId]
        );
        if ((int) $exists === 0) {
            $moduleId = $this->conn->fetchOne("SELECT id FROM app_module WHERE code = 'despacho'");
            if ($moduleId) {
                $this->conn->executeStatement(
                    "INSERT INTO app_role_module_permission (role_id, app_module_id, can_access) VALUES (?, ?, 1)",
                    [$adminId, $moduleId]
                );
                $io->info('Acceso al modulo "despacho" asignado a Administrador.');
            }
        }

        // Action permissions
        $functionCodes = ['shipments', 'dispatch_dashboard', 'paca_units'];
        $actions = $this->conn->fetchAllAssociative("SELECT id FROM app_action_catalog");

        foreach ($functionCodes as $funcCode) {
            $funcId = $this->conn->fetchOne("SELECT id FROM app_functionality WHERE code = ?", [$funcCode]);
            if (!$funcId) continue;

            foreach ($actions as $action) {
                $exists = $this->conn->fetchOne(
                    "SELECT COUNT(*) FROM app_role_action_permission WHERE role_id = ? AND app_function_id = ? AND action_id = ?",
                    [$adminId, $funcId, $action['id']]
                );
                if ((int) $exists === 0) {
                    $this->conn->executeStatement(
                        "INSERT INTO app_role_action_permission (role_id, app_function_id, action_id, allowed) VALUES (?, ?, ?, 1)",
                        [$adminId, $funcId, $action['id']]
                    );
                }
            }
            $io->info("Permisos de \"$funcCode\" asignados a Administrador.");
        }

        $io->success('Seed completado exitosamente.');

        return Command::SUCCESS;
    }
}
