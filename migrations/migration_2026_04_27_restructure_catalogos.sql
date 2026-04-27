-- ============================================================
-- Migración: Reestructuración de módulos - Catálogos centralizado
-- Fecha: 2026-04-27
-- Descripción: Mueve funciones de sus módulos originales al
--              módulo 'catalogos' según la nueva organización.
-- ============================================================

-- Obtener el ID del módulo catalogos para referencia
-- (Las subconsultas evitan hardcodear IDs)

-- Marcas: proveedor → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 4
WHERE code = 'brands';

-- Tipos de Bodega: almacenes → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 5
WHERE code = 'warehouse_types';

-- Motivos de Movimiento: inventario → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 6
WHERE code = 'inventory_reasons';

-- Etiquetas: productos → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 7
WHERE code = 'labels';

-- Calidades: productos → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 8
WHERE code = 'qualities';

-- Temporadas: productos → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 9
WHERE code = 'seasons';

-- Género: productos → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 10
WHERE code = 'genders';

-- Tipos de prenda: productos → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 11
WHERE code = 'garment_types';

-- Tipos de tela: productos → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 12
WHERE code = 'fabric_types';

-- Perfiles de talla: productos → catalogos
UPDATE app_function
SET app_module_id = (SELECT id FROM app_module WHERE code = 'catalogos'),
    display_order = 13
WHERE code = 'size_profiles';

-- Reordenar funciones que permanecen en sus módulos originales
UPDATE app_function SET display_order = 1 WHERE code = 'suppliers';
UPDATE app_function SET display_order = 1 WHERE code = 'pacas';
UPDATE app_function SET display_order = 1 WHERE code = 'warehouses';
UPDATE app_function SET display_order = 2 WHERE code = 'warehouse_bins';
UPDATE app_function SET display_order = 4 WHERE code = 'inventory_reservations';

-- ============================================================
-- Verificación: muestra el estado final por módulo
-- ============================================================
SELECT m.code AS modulo, f.code AS funcion, f.display_order AS orden
FROM app_function f
JOIN app_module m ON f.app_module_id = m.id
ORDER BY m.display_order, f.display_order;
