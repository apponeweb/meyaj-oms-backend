# Guia del Sistema de Inventario - Sprint 2

## Vision General

El Sprint 2 agrega **4 modulos nuevos** al sistema MEYAJ OMS, convirtiendo el sistema de ventas en un **ERP completo de inventario**. Todos los modulos estan integrados entre si a traves del **InventoryManager**, que es el punto unico de verdad para el control de stock.

```
                    +------------------+
                    | InventoryManager |
                    |  (Orquestador)   |
                    +--------+---------+
                             |
          +------------------+------------------+
          |                  |                  |
    +-----v------+    +-----v------+    +------v-----+
    |   Compras   |    |  Pedidos   |    |  Conteos   |
    | (Entrada)   |    |  (Salida)  |    | (Ajuste)   |
    +-----+------+    +-----+------+    +------+-----+
          |                  |                  |
          +------------------+------------------+
                             |
                    +--------v---------+
                    |      Kardex      |
                    | (Historial)      |
                    +------------------+
```

---

## Modulo 1: Almacenes

### Proposito
Gestiona las bodegas fisicas y sus ubicaciones internas (racks, estantes, columnas). Es la base para saber **donde** esta cada paca.

### Flujo de Uso

```
1. Crear Bodega
   Menu: Almacenes > Bodegas > [+ Crear]
   Campos: Empresa, Codigo (BOD-001), Nombre, Tipo, Direccion, Costo Mensual
   
2. Crear Ubicaciones
   Menu: Almacenes > Ubicaciones > [+ Crear]
   Campos: Bodega, Codigo (RACK-A1), Nombre, Zona, Tipo, Capacidad
   
3. Las pacas se asignan a una bodega y ubicacion
   (campos warehouse_id y warehouse_bin_id en la entidad Paca)
```

### Tipos de Bodega

| Tipo | Descripcion |
|------|-------------|
| **PROPIO** | Bodega propia de la empresa |
| **EXTERNO** | Bodega externa o rentada |
| **TEMPORAL** | Almacenamiento temporal |

### Pantallas

| Pantalla | Ruta | Funcionalidad |
|----------|------|---------------|
| Listado Bodegas | `/almacenes/warehouses` | Busqueda, filtro por tipo/estado, CRUD completo |
| Listado Ubicaciones | `/almacenes/bins` | Busqueda, filtro por bodega/estado, CRUD completo |

---

## Modulo 2: Inventario

Este es el modulo central. Tiene 4 secciones: **Kardex**, **Conteos Fisicos**, **Dashboard** y **Motivos**.

### 2.1 Kardex (Historial de Movimientos)

#### Proposito
El Kardex es el **libro mayor de inventario**. Registra CADA entrada y salida de stock con trazabilidad completa. Los movimientos son **inmutables** - nunca se editan ni eliminan.

#### Pantalla
**Ruta:** `/inventario/kardex`

#### Filtros disponibles
- Busqueda por paca (codigo o nombre)
- Filtro por bodega
- Filtro por tipo de movimiento (Entrada / Salida)
- Rango de fechas (Desde / Hasta)

#### Columnas de la tabla
| Columna | Descripcion |
|---------|-------------|
| Fecha | Fecha del movimiento |
| Paca | Codigo y nombre |
| Bodega | Donde ocurrio |
| Ubicacion | Ubicacion dentro de la bodega |
| Motivo | Razon del movimiento |
| Tipo | **Entrada** (verde) o **Salida** (rojo) |
| Entrada | Cantidad que ingreso |
| Salida | Cantidad que salio |
| Saldo | Stock despues del movimiento |
| Notas | Observaciones |
| Usuario | Quien registro |

#### Registrar Movimiento Manual
Boton **"Registrar Movimiento"** abre un dialogo:

1. Seleccionar Paca
2. Seleccionar Bodega
3. Seleccionar Ubicacion (filtrada por bodega)
4. Seleccionar Motivo (determina si es Entrada o Salida)
5. Cantidad
6. Costo unitario (opcional)
7. Notas (opcional)

**Regla de negocio:** Si el motivo es de tipo SALIDA, el sistema valida que haya stock suficiente. Si no hay, muestra error 409: *"Stock insuficiente para paca X. Stock actual: N, requerido: M"*.

#### Tipos de Movimiento (Motivos pre-cargados)

| Codigo | Nombre | Direccion | Uso |
|--------|--------|-----------|-----|
| `PURCHASE` | Compra de Proveedor | Entrada | Recepcion de mercancia |
| `SALE` | Venta | Salida | Cuando se envia un pedido |
| `RETURN` | Devolucion | Entrada | Devolucion de cliente |
| `LOSS` | Merma / Perdida | Salida | Productos danados o perdidos |
| `TRANSFER_IN` | Transferencia Entrada | Entrada | Recibe de otra bodega |
| `TRANSFER_OUT` | Transferencia Salida | Salida | Envia a otra bodega |
| `ADJUSTMENT_IN` | Ajuste Positivo | Entrada | Conteo fisico: se encontro mas |
| `ADJUSTMENT_OUT` | Ajuste Negativo | Salida | Conteo fisico: se encontro menos |
| `PHYSICAL_RECEIPT` | Recepcion Fisica | Entrada | Recepcion diaria de mercancia |

---

### 2.2 Conteos Fisicos

#### Proposito
Permite hacer **auditorias de inventario** comparando lo que dice el sistema vs lo que hay fisicamente. Si hay diferencias, el sistema genera automaticamente los ajustes (movimientos IN/OUT).

#### Flujo Completo

```
Paso 1: CREAR CONTEO
  Menu: Inventario > Conteos Fisicos > [+ Crear]
  Campos: Empresa, Bodega, Fecha de Conteo, Notas
  Estado: BORRADOR
  Folio: CNT-YYYYMMDD-NNNN (autogenerado)

Paso 2: INICIAR CONTEO
  Boton: [Iniciar] (icono Play)
  El sistema:
    - Busca todas las pacas activas en esa bodega
    - Crea un detalle por cada paca con la cantidad del sistema (system_qty)
    - Cambia estado a EN PROGRESO
  
Paso 3: CONTAR FISICAMENTE
  Para cada paca en la lista:
    - Ingresa la cantidad contada (counted_qty)
    - El sistema calcula automaticamente la diferencia
    - Opcionalmente agrega notas
  
Paso 4: FINALIZAR CONTEO
  Boton: [Finalizar]
  El sistema:
    - Valida que TODAS las pacas fueron contadas
    - Para cada diferencia != 0:
      - Si counted > system: genera movimiento ADJUSTMENT_IN
      - Si counted < system: genera movimiento ADJUSTMENT_OUT
    - Actualiza el stock de cada paca automaticamente
    - Registra cuantas discrepancias hubo
    - Cambia estado a COMPLETADO
```

#### Diagrama de Estados

```
BORRADOR ──[Iniciar]──> EN PROGRESO ──[Finalizar]──> COMPLETADO
    |                                                    
    └──[Eliminar]                                        
```

#### Ejemplo Practico

```
Bodega: BOD-001 (Almacen Central)
Paca: PAC-100 | Sistema dice: 50 unidades | Contaste: 47 unidades
  → Diferencia: -3
  → Se genera: ADJUSTMENT_OUT x 3 unidades
  → Stock paca se actualiza: 50 → 47

Paca: PAC-200 | Sistema dice: 10 unidades | Contaste: 12 unidades
  → Diferencia: +2
  → Se genera: ADJUSTMENT_IN x 2 unidades
  → Stock paca se actualiza: 10 → 12
```

---

### 2.3 Dashboard de Inventario

#### Proposito
Vista rapida del estado del inventario con indicadores clave y movimientos recientes.

**Ruta:** `/inventario/dashboard`

#### Tarjetas de Resumen

| Indicador | Descripcion | Color |
|-----------|-------------|-------|
| **Stock Total** | Suma del stock de todas las pacas | Azul |
| **Disponible** | Stock sin reservas activas | Verde |
| **Stock Bajo** | Pacas con stock entre 1 y 5 | Amarillo |
| **Agotado** | Pacas con stock = 0 | Rojo |

#### Tabla de Movimientos Recientes
Muestra los ultimos 10 movimientos del Kardex con filas coloreadas:
- **Verde claro** = Entradas
- **Rojo claro** = Salidas

---

### 2.4 Catalogo de Motivos

**Ruta:** `/inventario/reasons`

CRUD simple para gestionar los motivos de movimiento. Cada motivo tiene:
- Codigo unico
- Nombre
- Direccion (Entrada o Salida)
- Requiere referencia (si/no)
- Activo (si/no)

---

## Modulo 3: Compras (Ordenes de Compra)

### Proposito
Gestiona las ordenes de compra a proveedores. Permite registrar que se va a comprar, cuanto se espera recibir, y llevar control de recepciones parciales.

### Flujo Completo

```
Paso 1: CREAR ORDEN
  Menu: Compras > Ordenes de Compra > [+ Crear]
  Folio: PO-YYYY-NNNN (autogenerado, ej: PO-2026-0001)
  Estado: BORRADOR
  
  Datos del encabezado:
    - Empresa
    - Proveedor (de catalogo existente)
    - Fecha de orden
    - Fecha esperada de entrega
    - Notas
  
  Lineas de productos:
    - Descripcion
    - Cantidad esperada
    - Precio unitario
    - Etiqueta (opcional, del catalogo de labels)
  
  El sistema calcula automaticamente: Subtotal y Total

Paso 2: ENVIAR AL PROVEEDOR
  Cambiar estado a ENVIADA (desde el detalle)
  
Paso 3: RECIBIR MERCANCIA
  Boton: [Recibir] en la pagina de detalle
  Abre dialogo con la lista de productos:
    - Para cada linea: ingresar cantidad recibida
    - Soporta recepciones parciales
  
  El sistema:
    - Actualiza received_qty de cada linea
    - Si received >= expected: linea = RECIBIDA
    - Si received > 0 pero < expected: linea = PARCIAL
    - Si TODAS las lineas recibidas: PO = RECIBIDA
    - Si algunas recibidas: PO = PARCIAL

Paso 4: (Opcional) REGISTRAR EN KARDEX
  Actualmente la recepcion no genera movimientos de inventario
  automaticamente. Para impactar el stock, se debe hacer un
  movimiento manual en el Kardex con motivo PURCHASE.
```

### Diagrama de Estados

```
BORRADOR ──> ENVIADA ──> PARCIAL ──> RECIBIDA
    |            |          |
    └──> CANCELADA <───────┘
```

### Pantallas

| Pantalla | Ruta | Funcionalidad |
|----------|------|---------------|
| Listado | `/compras/purchase-orders` | Tabla con folio, proveedor, estado (badge coloreado), fechas, total, items |
| Detalle | `/compras/purchase-orders/:id` | Resumen, tarjetas financieras, tabla de items con barras de progreso, dialogo de recepcion |

### Filtros del Listado
- Busqueda por folio
- Proveedor (select)
- Estado (select)

---

## Modulo 4: Pedidos de Venta

### Proposito
Gestiona el ciclo de vida completo de un pedido: desde que el cliente pide, hasta que recibe la mercancia. Integrado con el sistema de **reservas de inventario** para evitar vender la misma paca dos veces.

### Flujo Completo

```
Paso 1: CREAR PEDIDO
  Menu: Pedidos > Pedidos de Venta > [+ Crear]
  Folio: SO-YYYY-NNNN (autogenerado, ej: SO-2026-0001)
  Estado: PENDIENTE
  
  Wizard de 3 pasos:
  
  Paso 1/3 - Datos del Cliente:
    - Empresa
    - Cliente (busqueda)
    - Canal (POS / Web / WhatsApp / Telefono)
    - Tipo (Estandar / Express / Mayoreo)
    - Vendedor (opcional)
    - Fecha estimada de entrega
    - Notas
  
  Paso 2/3 - Agregar Pacas:
    - Buscar pacas por codigo o nombre
    - Click para agregar al carrito
    - Editar cantidad, precio, descuento por linea
    - Ver totales en tiempo real
  
  Paso 3/3 - Revisar y Confirmar:
    - Resumen del pedido
    - Confirmar creacion
  
  Al crear:
    - Se RESERVA el stock de cada paca (InventoryReservation ACTIVE)
    - El stock disponible se reduce (pero el stock fisico no cambia)
    - Se crea historial: null → PENDIENTE

Paso 2: CONFIRMAR PEDIDO
  Boton: [Confirmar] en la pagina de detalle
  PENDIENTE → CONFIRMADO
  Se registra en el historial de estados

Paso 3: PREPARAR
  CONFIRMADO → PREPARANDO
  El equipo de almacen prepara la mercancia

Paso 4: ENVIAR
  Boton: [Enviar] en la pagina de detalle
  PREPARANDO → ENVIADO
  Al enviar:
    - Las reservas cambian a FULFILLED
    - El stock de cada paca se DECREMENTA
    - Es el momento donde el inventario fisico baja

Paso 5: ENTREGAR
  ENVIADO → ENTREGADO
  Se registra la fecha de entrega (delivered_at)
  Estado terminal.

CANCELAR (en cualquier momento antes de ENTREGADO):
  Boton: [Cancelar Pedido]
  Al cancelar:
    - Las reservas cambian a RELEASED
    - El stock reservado vuelve a estar disponible
    - Se registra en historial
```

### Diagrama de Estados

```
                         +─────────────+
                         |  PENDIENTE  |
                         +──────┬──────+
                    Confirmar   |   Cancelar
                         +──────v──────+──────> CANCELADO
                         | CONFIRMADO  |
                         +──────┬──────+
                    Preparar    |   Cancelar
                         +──────v──────+──────> CANCELADO
                         | PREPARANDO  |
                         +──────┬──────+
                    Enviar      |   Cancelar
                  (stock baja)  |
                         +──────v──────+──────> CANCELADO
                         |   ENVIADO   |
                         +──────┬──────+
                    Entregar    |   Devolver
                         +──────v──────+──────> DEVUELTO
                         |  ENTREGADO  |
                         +──────┬──────+
                         Devolver|
                         +──────v──────+
                         |  DEVUELTO   |
                         +─────────────+
```

### Concepto Clave: Reservas de Stock

```
Stock Total de PAC-001: 100 unidades

Pedido SO-2026-0001 pide 30 unidades → Reserva ACTIVA
Pedido SO-2026-0002 pide 25 unidades → Reserva ACTIVA

Stock Total:      100
Stock Reservado:   55  (30 + 25)
Stock Disponible:  45  (100 - 55)

Si alguien intenta crear un pedido por 50 unidades:
  → Error 409: "Stock disponible insuficiente. Disponible: 45, requerido: 50"
```

### Expiracion Automatica de Reservas

Las reservas pueden tener fecha de expiracion. Un comando Symfony corre periodicamente (cron cada 15 min recomendado) para liberar reservas expiradas:

```bash
# Ejecutar manualmente:
php bin/console app:reservations:expire

# Configurar en cron:
*/15 * * * * cd /path/to/project && php bin/console app:reservations:expire
```

### Pantallas

| Pantalla | Ruta | Funcionalidad |
|----------|------|---------------|
| Listado | `/pedidos/sales-orders` | Tabla con folio, cliente, canal (con icono), estado, pago, total. Filtros: estado, canal, estado pago |
| Detalle | `/pedidos/sales-orders/:id` | Resumen, info grid, tabla items, totales, **timeline visual de estados**, botones de accion |
| Crear | `/pedidos/sales-orders/new` | Wizard 3 pasos: cliente → items → confirmar |

### Timeline Visual de Estados
En la pagina de detalle, se muestra una linea de tiempo vertical con:
- Icono coloreado por estado
- Badge con el nombre del estado
- Nombre del usuario que hizo el cambio
- Fecha y hora
- Notas (si las hay)

---

## Modulo 5: Reportes de Inventario (Snapshots)

### Proposito
Genera "fotografias" diarias del estado del inventario por bodega, permitiendo analisis de tendencias a lo largo del tiempo.

**Ruta:** `/reportes/inventory`

### Datos que captura cada Snapshot

| Metrica | Descripcion |
|---------|-------------|
| Total Pacas | Numero de pacas activas en la bodega |
| Stock Total | Suma de unidades en stock |
| Valor de Compra | Sum(purchasePrice x stock) |
| Valor de Venta | Sum(sellingPrice x stock) |
| Movimientos IN | Entradas del dia |
| Movimientos OUT | Salidas del dia |
| Stock Bajo | Pacas con stock 1-5 |
| Agotado | Pacas con stock 0 |

### Generacion

```bash
# Generar snapshot de hoy:
php bin/console app:inventory:snapshot

# Generar snapshot de una fecha especifica:
php bin/console app:inventory:snapshot --date=2026-03-15

# Tambien disponible via API:
POST /api/inventario/snapshots/generate

# Configurar cron diario (recomendado a las 23:59):
59 23 * * * cd /path/to/project && php bin/console app:inventory:snapshot
```

### Pantalla
- 4 tarjetas de resumen (del snapshot mas reciente)
- Tabla con historial de snapshots: fecha, bodega, pacas, stock, movimientos IN/OUT, stock bajo, agotado, valor total

---

## Flujos de Negocio Integrados

### Flujo 1: Compra Completa (Proveedor → Bodega)

```
1. Crear Orden de Compra (PO-2026-0001)
   Compras > Ordenes de Compra > Crear
   Agregar items con cantidades esperadas

2. Enviar al Proveedor
   Cambiar estado a ENVIADA

3. Recibir Mercancia
   Boton Recibir > Ingresar cantidades recibidas

4. Registrar en Inventario (manual)
   Inventario > Kardex > Registrar Movimiento
   Motivo: "Compra de Proveedor"
   Cantidad = lo recibido
   → Stock de la paca AUMENTA
   → Queda registrado en el Kardex
```

### Flujo 2: Venta Completa (Pedido → Entrega)

```
1. Crear Pedido (SO-2026-0001)
   Pedidos > Crear nuevo pedido
   Seleccionar cliente, canal, agregar pacas
   → Stock se RESERVA (no baja aun)

2. Confirmar Pedido
   Detalle > Boton Confirmar
   PENDIENTE → CONFIRMADO

3. Preparar Envio
   CONFIRMADO → PREPARANDO (opcional)

4. Enviar
   Detalle > Boton Enviar
   PREPARANDO → ENVIADO
   → Reservas se CUMPLEN
   → Stock de cada paca BAJA

5. Confirmar Entrega
   ENVIADO → ENTREGADO
   → Se registra fecha de entrega
```

### Flujo 3: Conteo Fisico (Auditoria)

```
1. Crear Conteo
   Inventario > Conteos Fisicos > Crear
   Seleccionar bodega y fecha

2. Iniciar Conteo
   Boton Play > Sistema auto-popula pacas con stock del sistema

3. Contar Fisicamente
   Recorrer la bodega, ingresar cantidades reales

4. Finalizar
   Sistema compara y genera ajustes automaticos
   → Movimientos ADJUSTMENT_IN/OUT en el Kardex
   → Stock se corrige automaticamente
```

### Flujo 4: Transferencia entre Bodegas

```
1. Registrar Salida en bodega origen
   Kardex > Registrar Movimiento
   Motivo: "Transferencia Salida"
   → Stock baja en bodega origen

2. Registrar Entrada en bodega destino
   Kardex > Registrar Movimiento
   Motivo: "Transferencia Entrada"
   → Stock sube en bodega destino
```

---

## Sistema de Folios Automaticos

Cada entidad transaccional genera su folio automaticamente:

| Entidad | Formato | Ejemplo |
|---------|---------|---------|
| Orden de Compra | `PO-YYYY-NNNN` | PO-2026-0001 |
| Pedido de Venta | `SO-YYYY-NNNN` | SO-2026-0015 |
| Conteo Fisico | `CNT-YYYYMMDD-NNNN` | CNT-20260331-0042 |

Los folios son unicos, secuenciales por anio, y se generan automaticamente al crear.

---

## Seguridad y Permisos

Cada modulo nuevo esta integrado con el sistema RBAC existente:

| Modulo | Funcionalidades | Acciones |
|--------|----------------|----------|
| **Almacenes** | Bodegas, Ubicaciones | create, read, update, delete, export |
| **Inventario** | Kardex, Conteos Fisicos, Motivos, Dashboard | create, read, update, delete, export |
| **Compras** | Ordenes de Compra | create, read, update, delete, export |
| **Pedidos** | Pedidos de Venta, Seguimiento | create, read, update, delete, export |
| **Reportes** | Reportes de Inventario, Reportes de Compras | read, export |

Para dar acceso a un usuario:
1. Ir a **Seguridad > Roles > Permisos**
2. Seleccionar el rol
3. Activar los modulos nuevos
4. Marcar las acciones permitidas por funcionalidad

---

## Traducciones

Todo el sistema esta disponible en **Espanol** e **Ingles**. El cambio de idioma se hace desde el header de la aplicacion y afecta todas las pantallas, mensajes y etiquetas de los modulos nuevos.

---

## Comandos de Mantenimiento

| Comando | Descripcion | Frecuencia Recomendada |
|---------|-------------|----------------------|
| `php bin/console app:reservations:expire` | Libera reservas expiradas | Cada 15 minutos (cron) |
| `php bin/console app:inventory:snapshot` | Genera snapshot diario | Diario a las 23:59 (cron) |

---

## Calculo de Stock

```
Stock Fisico     = paca.stock (modificado solo por InventoryManager)
Stock Reservado  = SUM(reservas ACTIVAS para esa paca)
Stock Disponible = Stock Fisico - Stock Reservado
```

**Regla fundamental:** El stock fisico (`paca.stock`) SOLO se modifica a traves del InventoryManager. Nunca se edita directamente.
