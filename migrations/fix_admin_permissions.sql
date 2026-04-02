-- =================================================================
-- Fix: Regenerate all Admin permissions
-- Run this if the Administrador role lost permissions
-- =================================================================

-- 1. Grant access to ALL modules
INSERT INTO app_role_module_permission (role_id, app_module_id, can_access, created_at, updated_at)
SELECT r.id, m.id, 1, NOW(), NOW()
FROM app_role r
CROSS JOIN app_module m
WHERE r.name = 'Administrador'
  AND NOT EXISTS (
    SELECT 1 FROM app_role_module_permission rmp
    WHERE rmp.role_id = r.id AND rmp.app_module_id = m.id
  );

-- 2. Grant ALL actions on ALL functions
INSERT INTO app_role_action_permission (role_id, app_functionality_id, action_id, is_allowed, created_at, updated_at)
SELECT r.id, f.id, a.id, 1, NOW(), NOW()
FROM app_role r
CROSS JOIN app_functionality f
CROSS JOIN app_action_catalog a
WHERE r.name = 'Administrador'
  AND NOT EXISTS (
    SELECT 1 FROM app_role_action_permission rap
    WHERE rap.role_id = r.id AND rap.app_functionality_id = f.id AND rap.action_id = a.id
  );

-- 3. Verify
SELECT 'Module permissions' as type, COUNT(*) as total
FROM app_role_module_permission rmp
JOIN app_role r ON r.id = rmp.role_id
WHERE r.name = 'Administrador'
UNION ALL
SELECT 'Action permissions', COUNT(*)
FROM app_role_action_permission rap
JOIN app_role r ON r.id = rap.role_id
WHERE r.name = 'Administrador';
