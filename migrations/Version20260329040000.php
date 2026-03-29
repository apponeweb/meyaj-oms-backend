<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260329040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace product_catalogs with individual catalog functions, add security config functions, assign permissions';
    }

    public function up(Schema $schema): void
    {
        $now = date('Y-m-d H:i:s');

        // 1. Delete action permissions for product_catalogs function before removing it
        $this->addSql("DELETE rap FROM app_role_action_permission rap INNER JOIN app_functionality af ON rap.app_function_id = af.id WHERE af.code = 'product_catalogs'");
        $this->addSql("DELETE FROM app_functionality WHERE code = 'product_catalogs'");

        // 2. Add individual catalog functions under productos module (IGNORE duplicates)
        $catalogFunctions = [
            ['labels', 'Etiquetas', 2],
            ['qualities', 'Calidades', 3],
            ['seasons', 'Temporadas', 4],
            ['genders', 'Género', 5],
            ['garment_types', 'Tipos de prenda', 6],
            ['fabric_types', 'Tipos de tela', 7],
            ['size_profiles', 'Perfiles de talla', 8],
        ];

        foreach ($catalogFunctions as [$code, $name, $order]) {
            $this->addSql(
                "INSERT IGNORE INTO app_functionality (app_module_id, code, name, display_order, active, created_at, updated_at) " .
                "SELECT id, '{$code}', '{$name}', {$order}, 1, '{$now}', '{$now}' FROM app_module WHERE code = 'productos'"
            );
        }

        // 3. Add security config functions under seguridad module (IGNORE duplicates)
        $securityConfigFunctions = [
            ['modules_mgmt', 'Módulos', 5],
            ['functions_mgmt', 'Funcionalidades', 6],
            ['actions_mgmt', 'Acciones', 7],
        ];

        foreach ($securityConfigFunctions as [$code, $name, $order]) {
            $this->addSql(
                "INSERT IGNORE INTO app_functionality (app_module_id, code, name, display_order, active, created_at, updated_at) " .
                "SELECT id, '{$code}', '{$name}', {$order}, 1, '{$now}', '{$now}' FROM app_module WHERE code = 'seguridad'"
            );
        }

        // 4. Assign ALL action permissions to roles with access to productos module for catalog functions
        $allActions = ['create', 'read', 'update', 'delete', 'export'];
        foreach ($catalogFunctions as [$code, $name, $order]) {
            foreach ($allActions as $actionCode) {
                $this->addSql(
                    "INSERT IGNORE INTO app_role_action_permission (role_id, app_function_id, action_id, allowed) " .
                    "SELECT rmp.role_id, af.id, ac.id, 1 " .
                    "FROM app_role_module_permission rmp " .
                    "INNER JOIN app_module am ON rmp.app_module_id = am.id AND am.code = 'productos' " .
                    "INNER JOIN app_functionality af ON af.app_module_id = am.id AND af.code = '{$code}' " .
                    "INNER JOIN app_action_catalog ac ON ac.code = '{$actionCode}' " .
                    "WHERE rmp.can_access = 1"
                );
            }
        }

        // 5. Assign ONLY read permission for security config functions
        foreach ($securityConfigFunctions as [$code, $name, $order]) {
            $this->addSql(
                "INSERT IGNORE INTO app_role_action_permission (role_id, app_function_id, action_id, allowed) " .
                "SELECT rmp.role_id, af.id, ac.id, 1 " .
                "FROM app_role_module_permission rmp " .
                "INNER JOIN app_module am ON rmp.app_module_id = am.id AND am.code = 'seguridad' " .
                "INNER JOIN app_functionality af ON af.app_module_id = am.id AND af.code = '{$code}' " .
                "INNER JOIN app_action_catalog ac ON ac.code = 'read' " .
                "WHERE rmp.can_access = 1"
                );
        }

        // 6. Remove any non-read permissions for security config functions (enforce read-only)
        foreach ($securityConfigFunctions as [$code, $name, $order]) {
            $this->addSql(
                "DELETE rap FROM app_role_action_permission rap " .
                "INNER JOIN app_functionality af ON rap.app_function_id = af.id " .
                "INNER JOIN app_action_catalog ac ON rap.action_id = ac.id " .
                "WHERE af.code = '{$code}' AND ac.code != 'read'"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $now = date('Y-m-d H:i:s');

        // Remove catalog functions and their permissions
        $catalogCodes = "'labels','qualities','seasons','genders','garment_types','fabric_types','size_profiles'";
        $this->addSql("DELETE rap FROM app_role_action_permission rap INNER JOIN app_functionality af ON rap.app_function_id = af.id WHERE af.code IN ({$catalogCodes})");
        $this->addSql("DELETE FROM app_functionality WHERE code IN ({$catalogCodes})");

        // Remove security config functions and their permissions
        $secCodes = "'modules_mgmt','functions_mgmt','actions_mgmt'";
        $this->addSql("DELETE rap FROM app_role_action_permission rap INNER JOIN app_functionality af ON rap.app_function_id = af.id WHERE af.code IN ({$secCodes})");
        $this->addSql("DELETE FROM app_functionality WHERE code IN ({$secCodes})");

        // Restore product_catalogs
        $this->addSql(
            "INSERT INTO app_functionality (app_module_id, code, name, display_order, active, created_at, updated_at) " .
            "SELECT id, 'product_catalogs', 'Catálogos de producto', 2, 1, '{$now}', '{$now}' FROM app_module WHERE code = 'productos'"
        );
    }
}
