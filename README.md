# Tienda Demo

Tienda en línea desarrollada como un monolito con Laravel, React e Inertia. Incluye catálogo público, administración de productos, inventario por sucursal y búsqueda de productos con inteligencia artificial.

El proyecto está orientado a una empresa con varias sucursales. Los equipos (*teams*) permiten organizar miembros y permisos; la tienda pública muestra el catálogo del equipo configurado.

## Funcionalidades

- **Catálogo:** categorías, productos, variantes, códigos SKU, precios en bolivianos y galería de imágenes con carga múltiple mediante arrastrar y soltar.
- **Tienda pública:** filtros por nombre o código, categoría, sucursal y disponibilidad; ordenamiento y paginación. Los filtros se conservan al volver desde el detalle de un producto.
- **Búsqueda con IA:** campo independiente para describir lo que se quiere comprar y encontrar opciones del catálogo mediante similitud semántica.
- **Inventario:** existencias por variante y sucursal, con historial de movimientos.
- **Compras:** carrito, compra sin registro, selección de sucursal y pago al recoger.
- **Pedidos:** confirmación, cancelación, registro de entrega y pago, y devoluciones parciales con reposición opcional de existencias.
- **Administración:** miembros de equipo, roles, sucursales y filtros de pedidos.

## Tecnologías

| Área | Tecnología |
| --- | --- |
| Backend | Laravel 13 y PHP |
| Frontend | React 19, TypeScript e Inertia 3 |
| Interfaz | Tailwind CSS 4 y componentes Radix UI |
| Base de datos | PostgreSQL 16 con pgvector para búsqueda semántica |
| IA | Laravel AI SDK y embeddings de OpenAI |
| Herramientas | Vite Plus, Wayfinder, PHPUnit, Larastan y Pint |

## Requisitos

- PHP 8.3 o superior, con las extensiones necesarias para Laravel y `pdo_pgsql`. El entorno de desarrollo del proyecto utiliza PHP 8.5.
- Composer 2.
- Node.js 22.12 o superior y pnpm.
- PostgreSQL 16.
- Extensión **pgvector** instalada en el servidor PostgreSQL, compatible con esa versión.
- Una API key de OpenAI con saldo disponible si se desea activar la búsqueda semántica.

Las versiones exactas de las dependencias están registradas en `composer.lock` y `pnpm-lock.yaml`.

## Instalación local

Clona el repositorio y entra en su directorio. Después instala las dependencias:

```bash
composer install
pnpm install --frozen-lockfile
cp .env.example .env
php artisan key:generate
```

Crea una base de datos PostgreSQL y configura la conexión en `.env`:

```dotenv
APP_NAME="Tienda Demo"
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=online_store
DB_USERNAME=tu_usuario
DB_PASSWORD=

STOREFRONT_NAME="Tienda Demo"
STOREFRONT_TEAM_ID=
```

Las migraciones habilitan la extensión `vector`. Antes de ejecutarlas, pgvector debe estar instalado en el servidor y el usuario de la base de datos debe tener permisos para habilitarlo. Si no los tiene, un administrador debe ejecutar `CREATE EXTENSION IF NOT EXISTS vector;` en la base de datos de la aplicación.

Ejecuta las migraciones, carga los datos de ejemplo y publica el enlace de almacenamiento:

```bash
php artisan migrate --seed
php artisan storage:link
```

Los seeders crean el equipo **Tienda Demo**, un usuario administrador y 106 productos con sus variantes: seis productos iniciales y 100 productos adicionales de ropa, accesorios y regalos.

| Acceso de demostración | Valor |
| --- | --- |
| Correo | `admin@test.com` |
| Contraseña inicial | `password` |

Estas credenciales son para desarrollo. Si el usuario ya existe, el seeder conserva su contraseña actual.

Consulta el identificador del equipo creado:

```bash
php artisan tinker --execute 'dump(App\Models\Team::where("slug", "tienda-demo")->value("id"));'
```

Asigna ese valor a `STOREFRONT_TEAM_ID` en `.env`. La tienda pública necesita esta configuración para mostrar su catálogo.

Para descargar las imágenes del catálogo adicional:

```bash
php artisan catalog:import-demo --images
```

El comando utiliza datos de demostración basados en [DummyJSON](https://dummyjson.com/) y descarga las imágenes a `storage/app/public`. Se puede repetir para recuperar imágenes faltantes; conserva los productos ya importados y sus modificaciones. Los precios son de ejemplo.

Los seeders no crean sucursales ni existencias. Entra al panel, crea una sucursal y registra entradas de inventario para las variantes que quieras vender.

### Iniciar la aplicación

Ejecuta estos procesos en terminales separadas:

```bash
# Servidor de Laravel
php artisan serve
```

```bash
# Frontend con recarga durante el desarrollo
pnpm run dev
```

```bash
# Procesamiento de trabajos, incluida la actualización de embeddings
php artisan queue:work database --tries=3 --timeout=60
```

Abre la dirección configurada en `APP_URL` y utiliza **Acceso al panel** para administrar la tienda.

## Configurar la búsqueda con IA

La búsqueda por nombre y código funciona sin proveedor de IA. Para habilitar la búsqueda semántica, configura estas variables en `.env`:

```dotenv
SEMANTIC_SEARCH_ENABLED=true
SEMANTIC_SEARCH_PROVIDER=openai
SEMANTIC_SEARCH_MODEL=text-embedding-3-small
SEMANTIC_SEARCH_DIMENSIONS=1536
SEMANTIC_SEARCH_QUEUE_CONNECTION=database
OPENAI_API_KEY=tu_api_key
```

Limpia la configuración e indexa los productos del catálogo público:

```bash
php artisan config:clear
php artisan catalog:index-embeddings
```

El comando omite los embeddings que ya están vigentes. Para regenerarlos todos, por ejemplo después de cambiar el modelo o las dimensiones:

```bash
php artisan catalog:index-embeddings --force
```

Los cambios relevantes en productos, variantes y categorías programan una actualización del índice. Mantén el trabajador de colas en ejecución y reinícialo después de cambiar su configuración.

En el catálogo, pulsa **Consultar con IA** y escribe una idea, por ejemplo: «Busco un regalo para alguien que viaja y lleva su café al trabajo». La aplicación genera un embedding de la consulta y compara su similitud con los productos almacenados en PostgreSQL. Aplica también los filtros seleccionados.

Esta función recupera productos del catálogo; todavía no es un chat conversacional. Si el proveedor no está disponible, la consulta utiliza búsqueda textual como alternativa. Las llamadas a OpenAI consumen el saldo de la cuenta configurada.

## Verificación

```bash
# Pruebas con SQLite en memoria y sin activar llamadas reales de IA
SEMANTIC_SEARCH_ENABLED=false php artisan test --compact

# Análisis estático de PHP
composer types:check

# Comprobación de tipos de TypeScript
pnpm run types:check

# Comprobaciones del frontend
pnpm run check

# Compilación de producción
pnpm run build
```

La configuración de PHPUnit usa SQLite en memoria. Las pruebas específicas de similitud vectorial requieren una base PostgreSQL de pruebas con pgvector y se omiten al usar SQLite. Utiliza siempre una base separada de los datos de desarrollo o producción para ejecutar pruebas de integración.

## Estructura del proyecto

```text
app/Actions/            Lógica de inventario, pedidos y búsqueda semántica
app/Console/Commands/   Importación del catálogo e indexación de embeddings
app/Http/              Controladores, validación y middleware
app/Jobs/              Trabajos de actualización de embeddings
app/Models/            Modelos del negocio
config/                Configuración de la tienda y los proveedores
database/              Migraciones, factories y seeders
resources/js/          Páginas y componentes React
routes/                Rutas de Laravel
tests/                 Pruebas automatizadas
```

## Configuración del repositorio

El archivo `.env` contiene configuración privada y está excluido de Git. Usa `.env.example` como plantilla y guarda las credenciales de cada entorno fuera del repositorio. Las imágenes cargadas se almacenan en el disco público de Laravel y tampoco se incluyen en Git.
