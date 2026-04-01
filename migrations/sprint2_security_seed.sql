-- =================================================================
-- Sprint 2: Security Seed Data - AppModule + AppFunction + AppAction
-- =================================================================
-- Run this after Doctrine migrations to register new modules
-- in the security system (RBAC).
-- =================================================================

-- ── New AppModules ──
INSERT INTO app_module (code, name, icon, display_order, is_active, created_at, updated_at) VALUES
('almacenes',  'Almacenes',  'Warehouse',     7, 1, NOW(), NOW()),
('inventario', 'Inventario', 'ClipboardList', 8, 1, NOW(), NOW()),
('compras',    'Compras',    'ShoppingBag',   9, 1, NOW(), NOW()),
('pedidos',    'Pedidos',    'FileText',      10, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), display_order = VALUES(display_order);

-- ── New AppFunctions ──
-- Almacenes module
INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'warehouses', 'Bodegas', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'almacenes'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'warehouse_bins', 'Ubicaciones', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'almacenes'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Inventario module
INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'kardex', 'Kardex', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'inventario'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'inventory_counts', 'Conteos Físicos', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'inventario'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'inventory_reasons', 'Motivos de Movimiento', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'inventario'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'inventory_dashboard', 'Dashboard Inventario', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'inventario'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Compras module
INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'purchase_orders', 'Órdenes de Compra', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'compras'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Pedidos module
INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'sales_orders', 'Pedidos de Venta', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'pedidos'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'order_tracking', 'Seguimiento de Pedidos', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'pedidos'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Reportes module (add new functions to existing module)
INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'inventory_reports', 'Reportes de Inventario', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'reportes'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'purchase_reports', 'Reportes de Compras', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'reportes'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ── Seed InventoryReason catalog ──
INSERT INTO inventory_reason (code, name, direction, requires_reference, is_active, created_at, updated_at) VALUES
('PURCHASE',        'Compra de Proveedor',        'IN',  1, 1, NOW(), NOW()),
('SALE',            'Venta',                       'OUT', 1, 1, NOW(), NOW()),
('RETURN',          'Devolución',                  'IN',  1, 1, NOW(), NOW()),
('LOSS',            'Merma / Pérdida',             'OUT', 0, 1, NOW(), NOW()),
('TRANSFER_IN',     'Transferencia Entrada',       'IN',  1, 1, NOW(), NOW()),
('TRANSFER_OUT',    'Transferencia Salida',        'OUT', 1, 1, NOW(), NOW()),
('ADJUSTMENT_IN',   'Ajuste Positivo',             'IN',  0, 1, NOW(), NOW()),
('ADJUSTMENT_OUT',  'Ajuste Negativo',             'OUT', 0, 1, NOW(), NOW()),
('PHYSICAL_RECEIPT', 'Recepción Física',           'IN',  1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), direction = VALUES(direction);

-- =================================================================
-- NOTE: After running this seed, you need to assign permissions
-- to roles via the admin UI (Seguridad > Roles > Permisos).
-- Each new AppFunction will have standard actions:
-- create, read, update, delete, export
-- =================================================================
