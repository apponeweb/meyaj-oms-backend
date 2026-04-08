-- Seed: Modulo de Despacho + Funciones + Permisos para Admin
-- Ejecutar despues de las migraciones
-- Comando: php bin/console doctrine:query:sql "$(cat migrations/seed_despacho_module.sql)"
-- O ejecutar cada INSERT por separado con: php bin/console doctrine:query:sql "INSERT INTO ..."

-- 1. Crear modulo Despacho
INSERT IGNORE INTO app_module (code, name, icon, display_order, active, created_at, updated_at)
VALUES ('despacho', 'Despacho', 'Truck', 11, 1, NOW(), NOW());

-- 2. Crear funciones del modulo Despacho
INSERT IGNORE INTO app_functionality (code, name, app_module_id, display_order, active, created_at, updated_at)
SELECT 'shipments', 'Envios', m.id, 1, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'despacho';

INSERT IGNORE INTO app_functionality (code, name, app_module_id, display_order, active, created_at, updated_at)
SELECT 'dispatch_dashboard', 'Dashboard Despacho', m.id, 2, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'despacho';

-- 3. Crear funcion Unidades de Paca en modulo Inventario
INSERT IGNORE INTO app_functionality (code, name, app_module_id, display_order, active, created_at, updated_at)
SELECT 'paca_units', 'Unidades de Paca', m.id, 5, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'inventario';

-- 4. Asignar acceso al modulo Despacho para el rol Administrador
INSERT IGNORE INTO app_role_module_permission (role_id, app_module_id, can_access)
SELECT r.id, m.id, 1
FROM app_role r
CROSS JOIN app_module m
WHERE r.name = 'Administrador' AND m.code = 'despacho';

-- 5. Asignar permisos de funciones de Despacho para Administrador
INSERT IGNORE INTO app_role_action_permission (role_id, app_function_id, action_id, allowed)
SELECT r.id, f.id, a.id, 1
FROM app_role r
CROSS JOIN app_functionality f
CROSS JOIN app_action_catalog a
JOIN app_module m ON f.app_module_id = m.id
WHERE r.name = 'Administrador' AND m.code = 'despacho';

-- 6. Asignar permisos de paca_units para Administrador
INSERT IGNORE INTO app_role_action_permission (role_id, app_function_id, action_id, allowed)
SELECT r.id, f.id, a.id, 1
FROM app_role r
CROSS JOIN app_functionality f
CROSS JOIN app_action_catalog a
WHERE r.name = 'Administrador' AND f.code = 'paca_units';
