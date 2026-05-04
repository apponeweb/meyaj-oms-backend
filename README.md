# API POS - Sistema de Punto de Venta

API RESTful para gestión de punto de venta construida con **Symfony 7.4** y **PHP 8.2+**.

## Funcionalidades

- **Autenticación JWT** - Registro, login y control de acceso basado en roles
- **Productos** - CRUD completo con gestión de stock, precios, códigos de barra y filtrado por categoría/precio/estado
- **Categorías** - Organización jerárquica de productos
- **Clientes** - Gestión de datos de contacto, RUC/NIT y direcciones
- **Ventas** - Registro de transacciones con múltiples ítems y pagos (efectivo, tarjeta, transferencia, móvil)
- **Reportes** - Ventas diarias, mensuales, productos más vendidos, totales por método de pago
- **Documentación OpenAPI** - Swagger UI disponible en `/api/doc`

## Requisitos

- PHP >= 8.2
- Composer
- MySQL 8.0+
- OpenSSL (para claves JWT)

## Instalación

```bash
# Instalar dependencias
composer install

# Configurar variables de entorno
cp .env .env.local
# Editar .env.local con los datos de tu base de datos

# Crear base de datos y ejecutar migraciones
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Generar claves JWT
php bin/console lexik:jwt:generate-keypair

# Cargar datos iniciales (módulos, acciones, roles y permisos)
php bin/console app:seed-modules
php bin/console app:seed-despacho

# Iniciar servidor de desarrollo
symfony server:start
```

## Configuración operativa local y servidor

### Permisos de carpetas

Para que Symfony pueda generar caché, proxies de Doctrine y archivos temporales de importación, el usuario del servidor web debe tener permisos de escritura sobre:

```text
var/
var/cache/
var/log/
var/imports/
var/cache/prod/doctrine/orm/Proxies/
```

En Windows/IIS, validar permisos de escritura para el usuario que ejecuta el sitio o el application pool sobre la carpeta del proyecto y especialmente sobre `var/`.

### Carpetas que deben existir

Si no existen, deben crearse antes de usar importaciones o ejecutar en producción:

```text
var/
var/imports/
var/cache/
var/log/
var/cache/prod/doctrine/orm/Proxies/
```

### Carga de productos por Excel

La carga de productos usa la carpeta:

```text
var/imports
```

Si esta carpeta no existe o no tiene permisos de escritura, la importación puede fallar al subir o procesar archivos.

### Dependencias PHP requeridas para importación

Validar que la extensión `zip` esté habilitada en PHP para permitir el uso correcto de PhpSpreadsheet.

En Windows/Laragon normalmente se habilita en `php.ini`:

```ini
extension=zip
```

Después de habilitarla:

```bash
composer install
php bin/console cache:clear
```

### Limpiar caché después de cambios de configuración

Cada vez que se agreguen variables de entorno, cambien permisos o se creen carpetas requeridas, ejecutar:

```bash
php bin/console cache:clear
```

### Timeout de ejecución para importaciones largas

Si una carga de productos tarda más de 30 segundos, PHP puede responder con un error como:

```json
{
  "code": 500,
  "message": "Error interno del servidor.",
  "details": "Error: Maximum execution time of 30 seconds exceeded"
}
```

En el servidor de producción el parámetro principal se ajusta en:

```text
/etc/php.ini
```

Parámetro a revisar:

```ini
max_execution_time = 300
```

En este proyecto se actualizó en producción de `30` a `300` segundos para permitir importaciones más grandes.

Después de cambiarlo, reiniciar:

```bash
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

Si el servidor usa un pool o configuración adicional de PHP-FPM, también validar que no exista un límite más estricto en:

```text
/etc/php-fpm.d/www.conf
```

Parámetros a revisar allí:

```ini
request_terminate_timeout = 0
request_slowlog_timeout = 0
```

Si Nginx tuviera timeouts personalizados, validar también:

```text
fastcgi_read_timeout
proxy_read_timeout
```

### Implicaciones operativas y de costo

- **Mayor uso de CPU por request**
  - una importación larga mantiene un worker de PHP ocupado durante más tiempo

- **Menor concurrencia**
  - mientras una importación sigue corriendo, ese worker no atiende otras peticiones

- **Posible necesidad de más recursos**
  - si varios usuarios importan archivos pesados al mismo tiempo, puede ser necesario subir CPU/RAM o aumentar capacidad de la instancia

- **No aumenta el costo por sí solo**
  - cambiar `max_execution_time` no genera un cobro directo
  - el costo solo sube si la carga obliga a usar una instancia más grande, más workers o más infraestructura

- **Recomendación**
  - usar un valor moderado como `300` segundos para cargas grandes controladas
  - si las importaciones siguen creciendo, conviene moverlas a procesamiento en background en vez de seguir aumentando este timeout

## Sección de servidor: deploy y soporte postproducción

Esta sección documenta el flujo de despliegue validado en servidor y los puntos que deben revisarse en soporte si algo falla después de producción.

### Host y ruta validados

- host de API: `apioms.distribuidoradepacasjs.com`
- ruta del proyecto: `/var/www/backend`
- document root de nginx: `/var/www/backend/public`

### Servicios validados en servidor

Durante el despliegue se confirmó que los servicios activos usados por la API son:

```bash
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

No se detectó uso de `symfony server` en producción para servir la API.

### Variable de entorno crítica validada en producción

En el servidor se encontró:

```env
APP_OPERATION_MODE=INICIALIZANDO
```

Esto afecta comportamientos sensibles del módulo de pacas y eliminación de unidades.

Antes de cambiar esta variable, validar el objetivo operativo del ambiente:

- `INICIALIZANDO`
  - permite operaciones profundas útiles para setup o corrección de datos
- `PRODUCCION`
  - endurece restricciones para evitar borrados peligrosos

Si esta variable cambia o se agrega por primera vez, ejecutar después:

```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

### Flujo recomendado de deploy en servidor

Desde `/var/www/backend`:

```bash
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console app:seed-modules --env=prod
php bin/console app:seed-despacho --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

### Seeds que deben ejecutarse o validarse

En este proyecto hay datos base que el código asume existentes.

Ejecutar o validar:

```bash
php bin/console app:seed-modules --env=prod
php bin/console app:seed-despacho --env=prod
```

Además, si algo falla en inventario, despacho, devoluciones o importaciones, revisar que existan los motivos de inventario usados por código:

- `PURCHASE`
- `TRANSFER_IN`
- `TRANSFER_OUT`
- `LOSS`
- `ADJ_IN`
- `ADJ_OUT`
- `RESERVE`
- `RELEASE`
- `RETURN`
- `PHYSICAL`

Si faltan, el backend puede fallar al reservar, despachar, devolver, importar o registrar movimientos.

### Catálogo esperado de razones de inventario

El backend actual espera que existan estos registros en `inventory_reason` con estos códigos exactos:

| code | name | direction | is_active |
|------|------|-----------|-----------|
| `ADJUSTMENT_IN` | `Ajuste Entrada` | `IN` | `1` |
| `ADJUSTMENT_OUT` | `Ajuste Salida` | `OUT` | `1` |
| `LOSS` | `Baja` | `OUT` | `1` |
| `PHYSICAL_RECEIPT` | `Trazabilidad Física` | `IN` | `1` |
| `PURCHASE` | `Compra` | `IN` | `1` |
| `RELEASE` | `Liberación lógica` | `IN` | `1` |
| `RESERVE` | `Reserva lógica` | `OUT` | `1` |
| `RETURN` | `Devolución` | `IN` | `1` |
| `SALE` | `Venta` | `OUT` | `1` |
| `TRANSFER_IN` | `Transferencia Entrada` | `IN` | `1` |
| `TRANSFER_OUT` | `Transferencia Salida` | `OUT` | `1` |

Notas operativas:

- los `code` son sensibles y deben coincidir exactamente con los usados en el backend
- no basta con tener nombres parecidos si el `code` es distinto
- si el servidor tiene códigos legacy como `COMPRA`, `MOV-AJU` o `MOV-TRA`, eso no sustituye los códigos esperados por el backend actual
- para soporte, conviene agregar los códigos faltantes sin borrar primero los códigos legacy, y luego evaluar una limpieza controlada por separado

### Caso real detectado en deploy: migración desalineada con la base

Durante el despliegue se presentó este caso:

- Doctrine reportó pendiente la migración `Version20260501042000`
- esa migración intentaba crear la tabla `customer_import_log`
- en producción la tabla ya existía
- el esquema real de base ya estaba sincronizado con el modelo actual

Validación recomendada:

```bash
php bin/console doctrine:migrations:status --env=prod
php bin/console doctrine:schema:update --dump-sql --env=prod
```

Si `doctrine:schema:update --dump-sql` responde:

```text
[OK] Nothing to update - your database is already in sync with the current mapping file.
```

y aun así Doctrine marca una migración pendiente que intenta recrear una tabla ya existente, el problema puede ser **desalineación de historial de migraciones**, no del esquema.

En ese escenario:

- no forzar recreación de tabla
- no borrar tablas productivas para “hacer calzar” Doctrine
- primero validar si el esquema real ya coincide
- si coincide, registrar la migración pendiente en la tabla de versiones de Doctrine con criterio controlado

### Archivos locales modificados en servidor

Antes de hacer `git pull`, revisar si el servidor tiene cambios locales:

```bash
git status --short --branch
git diff
```

En el despliegue validado existía un cambio local en:

```text
config/reference.php
```

Para no romper la actualización, ese cambio local fue preservado temporalmente con `git stash` antes del `pull`.

Recomendación de soporte:

- si hay cambios locales en archivos de configuración generados o de referencia, preservarlos antes del deploy
- revisar después si realmente deben reaplicarse o si eran solo artefactos locales

### Validación rápida post deploy

1. Confirmar commit desplegado:

```bash
git rev-parse --short HEAD
```

2. Confirmar estado de migraciones:

```bash
php bin/console doctrine:migrations:status --env=prod
```

3. Confirmar servicios:

```bash
systemctl is-active php-fpm
systemctl is-active nginx
```

4. Confirmar que el vhost correcto apunte a `/var/www/backend/public`

```bash
sudo nginx -T | grep -E 'server_name|root '
```

5. Validar el login en el host real de producción, no solo contra `127.0.0.1`

Ruta válida detectada en backend:

```text
POST /api/login
```

Host validado:

```text
https://apioms.distribuidoradepacasjs.com/api/login
```

### Limpieza de caché y reinicio de servicios validado en producción

Si después de un deploy, ajuste de variables, inserción de catálogos o cambios de configuración el servidor sigue respondiendo con comportamiento anterior, ejecutar esta secuencia en `/var/www/backend`:

```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
sudo systemctl restart php-fpm
sudo systemctl restart nginx
```

Orden recomendado:

1. limpiar caché de Symfony
2. recalentar caché de Symfony
3. reiniciar `php-fpm`
4. reiniciar `nginx`

Comandos de validación que sí funcionaron en producción:

```bash
systemctl is-active php-fpm
systemctl is-active nginx
sudo nginx -T | grep -E 'server_name apioms\\.distribuidoradepacasjs\\.com|root /var/www/backend/public'
curl -k -I -s https://apioms.distribuidoradepacasjs.com/api/doc | head -n 5
```

Resultado esperado:

- `php-fpm` en estado `active`
- `nginx` en estado `active`
- vhost apuntando a `/var/www/backend/public`
- respuesta HTTP `200 OK` al consultar `https://apioms.distribuidoradepacasjs.com/api/doc`

Notas de soporte:

- este reinicio sí ayuda a descartar problemas de caché u `opcache`
- si después de esta secuencia el error sigue siendo exactamente el mismo, la causa ya no suele ser caché sino código activo o configuración funcional pendiente

### Qué revisar si algo falla después del deploy

- **si falla login con 404**
  - revisar que se esté probando contra el host/vhost correcto
  - revisar configuración de nginx y `root /var/www/backend/public`

- **si falla inventario o despacho por motivos no encontrados**
  - revisar seeds y catálogo de `inventory_reasons`

- **si falla importación**
  - revisar permisos en `var/` y `var/imports/`
  - revisar extensión PHP `zip`
  - revisar timeout de PHP

- **si falla eliminación de unidades/pacas**
  - revisar valor actual de `APP_OPERATION_MODE`

- **si falla una migración porque una tabla ya existe**
  - revisar `doctrine:migrations:status`
  - revisar `doctrine:schema:update --dump-sql --env=prod`
  - confirmar si el problema es de historial de migraciones y no de esquema real

## Variable de operación para inicialización y producción

Se agregó la variable de entorno:

```env
APP_OPERATION_MODE=INICIALIZANDO
```

Valores permitidos:

- `INICIALIZANDO`
- `PRODUCCION`

Si no se define, el backend asume:

```env
APP_OPERATION_MODE=PRODUCCION
```

### Comportamiento en `INICIALIZANDO`

- permite borrar unidades de paca en estados reservados, vendidos y otros usados durante setup inicial
- permite borrar una `paca` limpiando primero unidades, pedidos de venta, envíos y movimientos relacionados
- pensado solo para carga inicial, depuración de datos o reinicialización controlada

### Comportamiento en `PRODUCCION`

- solo permite borrar unidades `AVAILABLE`
- no permite borrar unidades `RESERVED` o `SOLD`
- no permite borrar una `paca` si todavía tiene unidades asociadas

### Dónde agregar la variable

Localmente puede agregarse en:

```text
.env.local
```

En servidor debe configurarse en las variables de entorno reales del ambiente o en el archivo de entorno correspondiente.

Ejemplo local para setup inicial:

```env
APP_OPERATION_MODE=INICIALIZANDO
```

Ejemplo cuando ya no debe permitir borrado profundo:

```env
APP_OPERATION_MODE=PRODUCCION
```

## Pasos recomendados después de agregar la variable

```bash
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
php bin/console app:seed-modules
```

Si se usa importación de productos y hay errores por clases faltantes o ZIP:

```bash
composer install
php bin/console cache:clear
```

## Migración incremental del flujo de pedidos, inventario y kardex

### Checklist de implementación

- [x] Incremento 1: preparar el dominio con nuevos estatus mínimos sin cambiar todavía la semántica operativa actual.
- [x] Incremento 2: mover la reserva fuera de la creación del pedido a un paso explícito de negocio.
- [x] Incremento 3: registrar movimientos lógicos de reserva y liberación sin afectar stock físico.
- [x] Incremento 4: endurecer validación por bodega y prevenir doble salida física por unidad/pedido.
- [x] Incremento 5: separar cancelación de devolución y habilitar devolución explícita.
- [x] Incremento 6: normalizar cache de stock y mantener consistencia entre unidades, kardex y respuestas API.
- [x] Incremento 7: habilitar devolución parcial explícita con selección de unidades y reconciliación de estatus.
- [x] Incremento 8: conciliar devoluciones explícitas con `paymentStatus` y cerrar operación de estatus de pago.

### Objetivo de la migración incremental

La ruta adoptada no reescribe el módulo completo. Se conserva la arquitectura actual basada en `SalesOrder`, `ShipmentOrder`, `PacaUnit` e `InventoryMovement`, y se harán ajustes graduales para alinear el backend con el modelo operativo deseado.

### Incremento 1: estatus nuevos incorporados al dominio

En este incremento se agregaron nuevos estatus al dominio para preparar los siguientes pasos, pero todavía no se cambió el flujo funcional existente de creación, reserva, surtido, envío o entrega.

#### Estatus de pedido (`SalesOrder`)

- `PENDING`
  - pedido creado y pendiente de procesamiento.
  - sigue siendo el estatus inicial actual.
- `CONFIRMED`
  - pedido confirmado comercialmente.
- `RESERVED`
  - nuevo estatus preparado para representar reserva explícita de inventario.
  - en este incremento todavía no se activa automáticamente en el flujo actual.
- `PREPARING`
  - pedido en preparación logística.
- `SHIPPED`
  - pedido con salida física registrada.
- `DELIVERED`
  - pedido entregado.
- `CANCELLED`
  - pedido cancelado.
- `RETURNED`
  - devolución total del pedido.
- `PARTIALLY_RETURNED`
  - nuevo estatus preparado para devoluciones parciales.
  - en este incremento todavía no se activa funcionalmente.

#### Estatus de unidad (`PacaUnit`)

- `AVAILABLE`
  - unidad disponible para reserva/venta.
- `RESERVED`
  - unidad apartada para un pedido.
- `PICKED`
  - estatus legado actualmente usado por el backend en despacho.
- `PICKING`
  - nuevo estatus preparado como equivalente semántico futuro para preparación logística.
  - en este incremento no sustituye todavía a `PICKED`.
- `DISPATCHED`
  - estatus legado actualmente usado por el backend cuando la unidad ya salió operativamente del almacén.
- `SHIPPED`
  - nuevo estatus preparado para representar salida física de forma más explícita.
  - en este incremento no sustituye todavía a `DISPATCHED`.
- `SOLD`
  - unidad vendida.
- `DELIVERED`
  - nuevo estatus preparado para reflejar entrega final a cliente.
  - en este incremento no se usa todavía en transiciones activas.
- `RETURNED`
  - unidad devuelta.
- `DAMAGED`
  - unidad dañada.
- `ADJUSTED_LOSS`
  - nuevo estatus preparado para pérdidas por ajuste físico.
- `CANCELLED`
  - nuevo estatus preparado para invalidación operativa de unidad.
- `IN_TRANSIT`
  - nuevo estatus preparado para traspasos o movimientos en tránsito.

### Compatibilidad actual

Durante el incremento 1, el backend mantiene comportamiento actual:

- la creación de `SalesOrder` sigue naciendo en `PENDING`
- el flujo actual de despacho sigue usando `PICKED` y `DISPATCHED`
- `RESERVED` y `PARTIALLY_RETURNED` quedan disponibles a nivel dominio pero aún no forman parte de todas las transiciones operativas
- `PICKING`, `SHIPPED`, `DELIVERED`, `ADJUSTED_LOSS`, `CANCELLED` e `IN_TRANSIT` en `PacaUnit` quedan preparados para incrementos siguientes

### Consideraciones para frontend en incremento 1

El frontend no debe asumir todavía que los nuevos estatus ya aparecerán en todas las respuestas operativas. En este incremento el objetivo es preparar contrato de dominio y documentación para los cambios siguientes.

Implicaciones inmediatas:

 - el frontend debe tolerar que en respuestas de pedido pueda aparecer en el futuro `RESERVED` y `PARTIALLY_RETURNED`
 - el frontend debe evitar hardcodear listas cerradas de estatus únicamente con los valores anteriores
 - el frontend que muestre estatus de unidad debe quedar listo para tolerar en el futuro `PICKING`, `SHIPPED`, `DELIVERED`, `ADJUSTED_LOSS`, `CANCELLED` e `IN_TRANSIT`
 - en este incremento no se requiere cambio visual obligatorio si el frontend ya renderiza etiquetas de estatus de manera dinámica

### Incremento 2: reserva explícita fuera de la creación del pedido

A partir de este incremento, el backend separa la creación del pedido del acto de reservar inventario.

#### Nuevo comportamiento backend

- `POST /api/pedidos/sales-orders`
  - crea el pedido y sus líneas
  - deja el pedido en `PENDING`
  - no asigna unidades
  - no reserva inventario en este paso

- `POST /api/pedidos/sales-orders/{id}/reserve`
  - ejecuta la reserva explícita de inventario
  - asigna unidades al pedido
  - cambia el pedido a `RESERVED`
  - actualmente usa la semántica disponible del backend existente, todavía sin validación explícita por bodega por línea

- `POST /api/despacho/shipments`
  - ahora espera que el pedido ya esté en `RESERVED`, `PREPARING` o `SHIPPED`
  - si el pedido está en `RESERVED`, el despacho lo mueve a `PREPARING`

#### Compatibilidad operativa

Este incremento cambia el flujo funcional de pedidos:

- crear pedido ya no aparta inventario
- la reserva pasa a ser un paso explícito
- si un cliente frontend intenta despachar un pedido recién creado sin reservar, backend debe rechazarlo

#### Consideraciones para frontend en incremento 2

Frontend debe ajustar el flujo de negocio de esta forma:

- crear pedido con `POST /api/pedidos/sales-orders`
- conservar el `id` del pedido creado
- si el flujo requiere apartar inventario, llamar inmediatamente después a `POST /api/pedidos/sales-orders/{id}/reserve`
- solo después de una reserva exitosa permitir acciones de despacho

Implicaciones técnicas:

 - no asumir que crear pedido equivale a reservar inventario
 - no asumir que un pedido recién creado ya tiene unidades asignadas
 - si la UI muestra detalle de unidades por item, esa colección puede venir vacía inmediatamente después del create

#### Compatibilidad actual

 - la reserva sigue cambiando unidades a `RESERVED`
 - la liberación sigue devolviendo unidades a `AVAILABLE`
 - la salida física real sigue registrándose con `SALE` en despacho
 - este incremento agrega auditabilidad, no cambia todavía validación por bodega ni endurece doble salida física

### Incremento 4: endurecimiento por bodega y prevención de doble salida física

En este incremento se endureció el módulo de despacho para bloquear escenarios inconsistentes antes de registrar salida física real.

#### Nuevo comportamiento backend

- un pedido no puede generar un nuevo envío activo si ya tiene otro envío en estado `PENDING`, `PICKING`, `PACKED` o `SHIPPED`
- al crear un envío, backend valida que las unidades reservadas del pedido pertenezcan a una sola bodega compatible con el envío solicitado
- si el pedido está en `RESERVED` pero no tiene unidades asignadas, backend rechaza la creación de envío
- al escanear una unidad, backend valida que la unidad pertenezca a la misma bodega del envío
- al enviar físicamente, backend rechaza envíos sin unidades escaneadas
- al enviar físicamente, backend exige que todas las unidades del envío sigan en `PICKED`
- al enviar físicamente, backend valida otra vez que cada unidad pertenezca a la bodega del envío antes de registrar `SALE`

#### Objetivo operativo

La intención de este incremento es evitar dos clases de error operativo:

- surtir o despachar desde una bodega distinta a la que realmente contiene las unidades reservadas
- registrar doble salida física por rehacer envíos activos o intentar despachar unidades fuera del estado esperado

#### Consideraciones para frontend en incremento 4

Frontend debe asumir que backend ahora es más estricto con el flujo de despacho.

- no intentar crear múltiples envíos activos para el mismo pedido
- no asumir que cualquier bodega seleccionada servirá para un pedido reservado
- si backend rechaza creación de envío por bodega inconsistente, la UI debe mostrar el mensaje y pedir corregir la selección
- no habilitar la acción de enviar si el envío todavía no tiene unidades escaneadas
- si una unidad reservada pertenece a otra bodega, el escaneo debe tratarse como error operativo y no como warning ignorable

### Incremento 6: normalización de cache de stock y consistencia de respuestas

En este incremento se normalizó la semántica de `cachedStock` para que represente el mismo universo que las respuestas API de stock y los conteos físicos sustentados por `PacaUnit`.

#### Nuevo comportamiento backend

- `cachedStock` ahora queda alineado con el conteo de unidades rastreables del backend
- el universo rastreable usado por backend queda normalizado a unidades en `AVAILABLE`, `RESERVED` y `PICKED`
- `PacaResponse.stock`, `trackedStock` y `stockByWarehouse.total` ahora usan esa misma definición
- la devolución explícita ya no incrementa artificialmente `cachedStock`
- la recepción de órdenes de compra recalcula `cachedStock` al final de materializar unidades
- los movimientos manuales de inventario quedan bloqueados para evitar desincronizar kardex contra unidades reales

#### Objetivo operativo

La intención de este incremento es eliminar escenarios donde:

- el kardex mostraba un saldo distinto al stock sustentado por unidades reales
- el `cachedStock` de una paca no coincidía con lo que devolvía la API en listados o detalle
- una devolución o ajuste parcial alteraba saldo sin respaldo en `PacaUnit`

#### Consideraciones para frontend en incremento 6

Frontend debe asumir que el stock expuesto por backend ahora es más estricto y consistente.

- `stock` y `stockByWarehouse.total` ya no deben interpretarse como “cualquier unidad no vendida”, sino como stock rastreable vigente
- una unidad en `RETURNED`, `DISPATCHED`, `SOLD` o `DAMAGED` ya no debe contarse como stock disponible/rastreable en UI
- si existía una UI para crear movimientos manuales de inventario, debe deshabilitarse o esconderse
- para ajustes reales de inventario, frontend debe canalizar al usuario a recepción, despacho, devolución explícita o conteo físico

#### Compatibilidad actual

- el backend sigue conservando el endpoint de consulta de kardex
- el endpoint de creación manual de movimientos ahora rechaza operaciones por consistencia operativa
- el flujo de reserva y liberación lógica no cambia stock físico ni `cachedStock`
- este incremento deja más claro el terreno para un siguiente paso de ajustes unitarios explícitos si se requieren

### Incremento 7: devolución parcial explícita por unidad

En este incremento se habilitó una operación explícita para devolver solo una parte de las unidades ya despachadas o vendidas de un pedido, sin obligar a devolver todo el pedido completo.

#### Nuevo comportamiento backend

- se agregó `POST /api/pedidos/sales-orders/{id}/partial-return`
- el request recibe una lista de `unitIds` específicas a devolver
- backend valida que cada unidad pertenezca al pedido y esté en estado retornable (`DISPATCHED` o `SOLD`)
- las unidades seleccionadas pasan a `RETURNED`
- se registra el kardex `RETURN` solo por las unidades seleccionadas
- el pedido pasa a `PARTIALLY_RETURNED` si aún quedan unidades retornables pendientes
- el pedido pasa a `RETURNED` si con esa operación ya no quedan unidades retornables en el pedido
- la devolución total existente sigue funcionando y ahora también acepta pedidos que ya estaban en `PARTIALLY_RETURNED`

#### Objetivo operativo

La intención de este incremento es separar estos dos casos:

- devolver todo el pedido en una sola operación
- devolver solo algunas unidades específicas sin cerrar todavía toda la devolución del pedido

#### Consideraciones para frontend en incremento 7

Frontend debe distinguir claramente entre devolución total y devolución parcial.

- para devolución total, seguir usando `POST /api/pedidos/sales-orders/{id}/return`
- para devolución parcial, usar `POST /api/pedidos/sales-orders/{id}/partial-return`
- la UI debe permitir seleccionar unidades concretas del pedido para devolver
- no enviar ids de unidades que no pertenezcan al pedido actual
- no ofrecer para devolución parcial unidades ya devueltas o que no estén en estado retornable
- si después de una devolución parcial ya no quedan unidades retornables, frontend debe tolerar que el backend responda el pedido ya en `RETURNED`

#### Compatibilidad actual

- `PARTIALLY_RETURNED` deja de ser solo un estatus preparado y pasa a formar parte activa del flujo operativo
- el endpoint de cambio genérico de status sigue sin permitir forzar `RETURNED` ni `PARTIALLY_RETURNED`
- el detalle del pedido sigue exponiendo las unidades por línea, por lo que frontend puede reutilizar esa información para selección
- este incremento deja lista la base para soportar después reglas más finas de reembolso o devolución por motivo

### Incremento 8: conciliación de devoluciones con estado de pago

En este incremento se cerró la brecha entre la devolución física del pedido y su semántica financiera mínima, aprovechando el `paymentStatus` ya existente en el dominio.

#### Nuevo comportamiento backend

- la devolución total explícita ahora ajusta `paymentStatus` cuando corresponde
- si un pedido estaba `PAID` o `PARTIAL` y termina en `RETURNED`, backend lo mueve a `REFUNDED`
- si un pedido estaba `PAID` y se registra una devolución parcial, backend lo mueve a `PARTIAL`
- se implementó el método de servicio `changePaymentStatus()` que ya estaba expuesto por controller
- el endpoint `POST /api/pedidos/sales-orders/{id}/payment-status` sigue permitiendo cambios manuales operativos a `PENDING`, `PARTIAL` y `PAID`
- backend bloquea asignar manualmente `REFUNDED` desde ese endpoint; ese estado queda reservado para devoluciones explícitas

#### Objetivo operativo

La intención de este incremento es evitar que el pedido quede con inventario devuelto pero con semántica financiera incongruente, por ejemplo:

- pedido totalmente devuelto pero aún marcado como `PAID`
- pedido parcialmente devuelto pero sin reflejar que el estado de pago ya no es íntegramente cobrado

#### Consideraciones para frontend en incremento 8

Frontend debe tratar `paymentStatus` como un eje separado pero ahora parcialmente automatizado por backend.

- después de una devolución total, la UI debe tolerar que `paymentStatus` cambie a `REFUNDED`
- después de una devolución parcial de un pedido previamente `PAID`, la UI debe tolerar que `paymentStatus` cambie a `PARTIAL`
- la UI no debe ofrecer `REFUNDED` como opción manual en formularios de cambio de estado de pago
- si existe selector manual de `paymentStatus`, limitarlo a `PENDING`, `PARTIAL` y `PAID`
- para mostrar resumen del pedido, combinar visualmente `status` y `paymentStatus`, porque ya no evolucionan de manera totalmente independiente

#### Compatibilidad actual

- este incremento no implementa todavía cálculo monetario fino de cuánto se reembolsó
- `REFUNDED` se usa como conciliación operativa mínima, no como ledger financiero detallado
- la base queda lista para un siguiente paso con montos reembolsados o motivos de devolución/reembolso más específicos

## Estructura del proyecto

```
src/
├── Controller/      # Controladores de la API (Auth, Product, Category, Sale, User, Customer)
├── Entity/          # Entidades Doctrine (User, Product, Category, Sale, SaleItem, Customer, Payment)
├── Service/         # Lógica de negocio
├── Repository/      # Acceso a datos
├── DTO/
│   ├── Request/     # Validación de entrada
│   └── Response/    # Transformación de salida
├── Pagination/      # Utilidades de paginación
└── EventListener/   # Manejo centralizado de errores
```

## Endpoints principales

| Recurso    | Método | Ruta                    | Descripción              |
|------------|--------|-------------------------|--------------------------|
| Auth       | POST   | `/api/auth/register`    | Registro de usuario      |
| Auth       | POST   | `/api/auth/login`       | Inicio de sesión (JWT)   |
| Productos  | GET    | `/api/products`         | Listar productos         |
| Productos  | POST   | `/api/products`         | Crear producto           |
| Categorías | GET    | `/api/categories`       | Listar categorías        |
| Ventas     | POST   | `/api/sales`            | Registrar venta          |
| Ventas     | GET    | `/api/sales/report/daily` | Reporte diario         |
| Clientes   | GET    | `/api/customers`        | Listar clientes          |

## Tecnologías

- **Symfony 7.4** - Framework PHP
- **Doctrine ORM 3.6** - Mapeo objeto-relacional
- **Lexik JWT Bundle** - Autenticación por tokens
- **Nelmio API Doc** - Documentación OpenAPI/Swagger
- **Nelmio CORS Bundle** - Soporte para peticiones cross-origin
