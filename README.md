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

# Iniciar servidor de desarrollo
symfony server:start
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
