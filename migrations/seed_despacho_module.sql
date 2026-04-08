-- Seed: Modulo de Despacho + Funciones + Permisos para Admin
-- Ejecutar despues de la migracion Phase 3

-- 1. Crear modulo Despacho
INSERT INTO app_module (code, name, icon, display_order, is_active, created_at, updated_at)
VALUES ('despacho', 'Despacho', 'Truck', 11, 1, NOW(), NOW());

-- 2. Crear funciones del modulo
INSERT INTO app_function (code, name, app_module_id, display_order, is_active, created_at, updated_at)
SELECT 'shipments', 'Envíos', m.id, 1, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'despacho';

INSERT INTO app_function (code, name, app_module_id, display_order, is_active, created_at, updated_at)
SELECT 'dispatch_dashboard', 'Dashboard Despacho', m.id, 2, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'despacho';

-- 3. Asignar acceso al modulo para el rol Administrador
INSERT INTO app_role_module_permission (role_id, app_module_id, can_access)
SELECT r.id, m.id, 1
FROM app_role r
CROSS JOIN app_module m
WHERE r.name = 'Administrador' AND m.code = 'despacho';

-- 4. Asignar permisos de funciones para el rol Administrador
INSERT INTO app_role_action_permission (role_id, app_function_id, action_id, allowed)
SELECT r.id, f.id, a.id, 1
FROM app_role r
CROSS JOIN app_function f
CROSS JOIN action_catalog a
JOIN app_module m ON f.app_module_id = m.id
WHERE r.name = 'Administrador' AND m.code = 'despacho';

-- 5. Tambien agregar la funcion 'paca_units' al modulo de inventario
INSERT INTO app_function (code, name, app_module_id, display_order, is_active, created_at, updated_at)
SELECT 'paca_units', 'Unidades de Paca', m.id, 5, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'inventario';

-- 6. Permisos de paca_units para Administrador
INSERT INTO app_role_action_permission (role_id, app_function_id, action_id, allowed)
SELECT r.id, f.id, a.id, 1
FROM app_role r
CROSS JOIN app_function f
CROSS JOIN action_catalog a
WHERE r.name = 'Administrador' AND f.code = 'paca_units';
