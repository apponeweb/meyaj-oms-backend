-- =================================================================
-- Migration: 2026-04-02
-- Changes: warehouse_type catalog, paca multi-location, reservations
-- =================================================================
-- IMPORTANT: Run this script in order. Each section depends on the previous.
-- =================================================================

-- ── 1. Create warehouse_type table ──
CREATE TABLE IF NOT EXISTS warehouse_type (
  id INT AUTO_INCREMENT NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE INDEX UNIQ_warehouse_type_name (name),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4;

-- ── 2. Seed warehouse_type with default values ──
INSERT INTO warehouse_type (name, description, is_active, created_at, updated_at) VALUES
('Propio',   'Bodega propia de la empresa',              1, NOW(), NOW()),
('Externo',  'Bodega externa o de terceros',              1, NOW(), NOW()),
('Temporal', 'Bodega temporal para almacenamiento corto', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ── 3. Migrate warehouse.warehouse_type string → FK ──
-- 3a. Add new column
ALTER TABLE warehouse ADD warehouse_type_id INT DEFAULT NULL;

-- 3b. Map existing string values to FK IDs
UPDATE warehouse w
SET w.warehouse_type_id = (
  SELECT wt.id FROM warehouse_type wt
  WHERE wt.name = CASE w.warehouse_type
    WHEN 'PROPIO' THEN 'Propio'
    WHEN 'EXTERNO' THEN 'Externo'
    WHEN 'TEMPORAL' THEN 'Temporal'
    ELSE w.warehouse_type
  END
  LIMIT 1
);

-- 3c. Fallback: assign 'Propio' to any unmapped rows
UPDATE warehouse SET warehouse_type_id = (SELECT id FROM warehouse_type WHERE name = 'Propio' LIMIT 1)
WHERE warehouse_type_id IS NULL;

-- 3d. Make column NOT NULL and add FK
ALTER TABLE warehouse MODIFY warehouse_type_id INT NOT NULL;
ALTER TABLE warehouse ADD CONSTRAINT FK_warehouse_type FOREIGN KEY (warehouse_type_id) REFERENCES warehouse_type (id);
CREATE INDEX IDX_warehouse_type ON warehouse (warehouse_type_id);

-- 3e. Drop old string column
ALTER TABLE warehouse DROP COLUMN warehouse_type;

-- ── 4. Create paca_location table ──
CREATE TABLE IF NOT EXISTS paca_location (
  id INT AUTO_INCREMENT NOT NULL,
  paca_id INT NOT NULL,
  warehouse_id INT NOT NULL,
  warehouse_bin_id INT DEFAULT NULL,
  created_at DATETIME NOT NULL,
  INDEX IDX_paca_location_paca (paca_id),
  INDEX IDX_paca_location_warehouse (warehouse_id),
  INDEX IDX_paca_location_bin (warehouse_bin_id),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4;

ALTER TABLE paca_location ADD CONSTRAINT FK_paca_location_paca FOREIGN KEY (paca_id) REFERENCES paca (id) ON DELETE CASCADE;
ALTER TABLE paca_location ADD CONSTRAINT FK_paca_location_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouse (id);
ALTER TABLE paca_location ADD CONSTRAINT FK_paca_location_bin FOREIGN KEY (warehouse_bin_id) REFERENCES warehouse_bin (id) ON DELETE SET NULL;

-- ── 5. Migrate paca warehouse/bin → paca_location ──
INSERT INTO paca_location (paca_id, warehouse_id, warehouse_bin_id, created_at)
SELECT p.id, p.warehouse_id, p.warehouse_bin_id, NOW()
FROM paca p
WHERE p.warehouse_id IS NOT NULL;

-- ── 6. Drop old warehouse columns from paca ──
ALTER TABLE paca DROP FOREIGN KEY IF EXISTS FK_770BB73D5080ECDE;
ALTER TABLE paca DROP FOREIGN KEY IF EXISTS FK_770BB73DC77159AD;
DROP INDEX IF EXISTS IDX_770BB73D5080ECDE ON paca;
DROP INDEX IF EXISTS IDX_770BB73DC77159AD ON paca;
ALTER TABLE paca DROP COLUMN IF EXISTS warehouse_id;
ALTER TABLE paca DROP COLUMN IF EXISTS warehouse_bin_id;

-- ── 7. Register new RBAC functions ──
-- warehouse_types
INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'warehouse_types', 'Tipos de Bodega', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'almacenes'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- inventory_reservations
INSERT INTO app_function (code, name, app_module_id, is_active, created_at, updated_at)
SELECT 'inventory_reservations', 'Reservas de Inventario', m.id, 1, NOW(), NOW()
FROM app_module m WHERE m.code = 'inventario'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =================================================================
-- After running this migration, execute:
--   php bin/console app:seed-modules
-- This will auto-assign permissions to the Admin role.
-- =================================================================
