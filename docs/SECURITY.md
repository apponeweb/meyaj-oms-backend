# Sistema de Seguridad y Permisos - Pacas Pro

Guia completa del sistema de seguridad basado en roles, modulos, funcionalidades y acciones.

## Arquitectura

```
Usuario
  └── Rol (app_role)
        └── Acceso a Modulos (app_role_module_permission)
              └── Modulo (app_module)
                    └── Funcionalidades (app_functionality)
                          └── Acciones por funcionalidad (app_role_action_permission)
                                └── Accion (app_action_catalog)
```

## Modelo de datos

### Tablas principales

| Tabla | Descripcion |
|-------|-------------|
| `app_module` | Modulos del sistema (Catalogos, Seguridad, Ventas, etc.) |
| `app_functionality` | Funcionalidades dentro de cada modulo (Empresas, Sucursales, Pacas, etc.) |
| `app_action_catalog` | Acciones disponibles (crear, leer, actualizar, eliminar, exportar) |
| `app_role` | Roles del sistema (Administrador, Vendedor, etc.) |
| `app_role_module_permission` | Que modulos puede ver cada rol |
| `app_role_action_permission` | Que acciones puede hacer cada rol sobre cada funcionalidad |

### Relaciones

```
app_user.role_id ──> app_role.id

app_role_module_permission
  ├── role_id ──> app_role.id
  └── app_module_id ──> app_module.id

app_role_action_permission
  ├── role_id ──> app_role.id
  ├── app_function_id ──> app_functionality.id
  └── action_id ──> app_action_catalog.id
```

## Flujo completo

### 1. Crear una funcionalidad

Desde la UI en `/seguridad/functions` o directamente en base de datos:

```sql
INSERT INTO app_functionality (app_module_id, code, name, display_order, active, created_at, updated_at)
SELECT id, 'warehouses', 'Almacenes', 4, 1, NOW(), NOW()
FROM app_module WHERE code = 'catalogos';
```

Campos requeridos:
- `app_module_id`: modulo al que pertenece
- `code`: identificador unico (se usa en el frontend para `usePermissions("code")`)
- `name`: nombre visible en la UI

### 2. Asignar permisos al rol

Desde la UI en `/seguridad/permissions` o por SQL:

```sql
-- Dar acceso al modulo (si no lo tiene ya)
INSERT IGNORE INTO app_role_module_permission (role_id, app_module_id, can_access)
VALUES (1, (SELECT id FROM app_module WHERE code = 'catalogos'), 1);

-- Dar permisos sobre la funcionalidad
INSERT INTO app_role_action_permission (role_id, app_function_id, action_id, allowed)
SELECT 1, f.id, a.id, 1
FROM app_functionality f
CROSS JOIN app_action_catalog a
WHERE f.code = 'warehouses';
```

### 3. Como se consumen los permisos

Cuando el usuario se autentica, el frontend llama a `GET /api/modules`. El `PermissionService` del backend:

1. Obtiene el rol del usuario
2. Busca los modulos con `canAccess = true` para ese rol
3. Para cada modulo, obtiene sus funcionalidades
4. Para cada funcionalidad, obtiene las acciones permitidas

Respuesta:

```json
{
  "modules": [
    {
      "code": "catalogos",
      "name": "Catalogos",
      "icon": "building",
      "functions": [
        {
          "code": "warehouses",
          "name": "Almacenes",
          "permissions": {
            "create": true,
            "read": true,
            "update": true,
            "delete": false,
            "export": false
          }
        }
      ]
    }
  ]
}
```

## Integracion con el frontend

### Hook de permisos

Cada pagina CRUD usa el hook `usePermissions` con el codigo de la funcionalidad:

```tsx
const { canCreate, canRead, canUpdate, canDelete, canExport } = usePermissions("warehouses")
```

### Controlar visibilidad de elementos

```tsx
// Boton Crear: solo visible si canCreate = true
{canCreate && (
  <Button onClick={() => setDialogOpen(true)}>
    <Plus /> Crear
  </Button>
)}

// Boton Editar: solo visible si canUpdate = true
{canUpdate && <Button><Pencil /></Button>}

// Boton Eliminar: solo visible si canDelete = true
{canDelete && <AlertDialog>...</AlertDialog>}

// Columna de acciones: se oculta si no hay ni editar ni eliminar
{(canUpdate || canDelete) && <TableHead>Acciones</TableHead>}
```

### Menu lateral (sidebar)

Cada item del sidebar tiene un `functionCode` en `src/config/modules.ts`:

```ts
{
  titleKey: "modules.warehouses",
  path: "/catalogos/warehouses",
  icon: Warehouse,
  functionCode: "warehouses"  // <-- vinculo con app_functionality.code
}
```

Si la funcionalidad no tiene permiso `read`, **el item no aparece** en el menu.

### Acceso a modulo (inferido)

El acceso al modulo se infiere automaticamente: si al menos una funcionalidad del modulo tiene al menos una accion marcada, el modulo se considera activo. No se configura manualmente.

## Reglas de negocio

### Dependencia de acciones

- **Leer (read)** es obligatorio para cualquier otra accion
- Al marcar crear, editar, eliminar o exportar, leer se marca automaticamente
- Al desmarcar leer, todas las demas acciones se desmarcan

### Usuario sin rol

Si un usuario no tiene rol asignado (`role_id = NULL`), no tiene acceso a ningun modulo ni funcionalidad.

## Gestion desde la UI

| Funcionalidad | Ruta | Descripcion |
|---|---|---|
| Modulos | `/seguridad/modules` | Crear, editar, eliminar modulos del sistema |
| Funcionalidades | `/seguridad/functions` | Crear, editar, eliminar funcionalidades por modulo |
| Acciones | `/seguridad/actions` | Crear, editar, eliminar acciones (crear, leer, etc.) |
| Permisos | `/seguridad/permissions` | Asignar acciones por funcionalidad a cada rol |
| Roles | `/seguridad/roles` | Crear y administrar roles |
| Usuarios | `/seguridad/users` | Asignar roles a usuarios |

## Agregar una nueva funcionalidad al sistema (checklist)

| Paso | Donde | Que hacer |
|------|-------|-----------|
| 1 | `/seguridad/functions` | Crear la funcionalidad con codigo y modulo |
| 2 | `/seguridad/permissions` | Asignar acciones al rol correspondiente |
| 3 | `src/config/modules.ts` | Agregar item al sidebar con `functionCode` |
| 4 | `src/pages/{modulo}/index.tsx` | Agregar la ruta `<Route path="..." element={...} />` |
| 5 | Pagina CRUD | Usar `usePermissions("codigo")` para controlar botones |

## Variables de entorno relacionadas

```env
# Tiempo de inactividad antes de cerrar sesion (minutos)
SESSION_TIMEOUT_MINUTES=30
```

## Endpoints de seguridad

| Metodo | Ruta | Descripcion |
|--------|------|-------------|
| GET | `/api/modules` | Modulos y permisos del usuario autenticado |
| GET | `/api/session/config` | Configuracion de timeout de sesion |
| POST | `/api/logout` | Cerrar sesion (marca la sesion como inactiva) |
| GET | `/api/seguridad/roles` | Listar roles |
| GET | `/api/seguridad/roles/{id}` | Detalle de rol con permisos |
| PUT | `/api/seguridad/roles/{id}/permissions` | Actualizar permisos de un rol |
| GET | `/api/seguridad/roles/meta` | Modulos, funcionalidades y acciones disponibles |
| GET | `/api/seguridad/modules` | CRUD de modulos |
| GET | `/api/seguridad/functions` | CRUD de funcionalidades |
| GET | `/api/seguridad/actions` | CRUD de acciones |
| GET | `/api/seguridad/sessions` | Sesiones activas e historial |
