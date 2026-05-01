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
