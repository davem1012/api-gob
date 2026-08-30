# API Gob

API en PHP (Slim Framework 4) que expone endpoints de consulta a servicios públicos peruanos y utilidades relacionadas:

- `GET /api/ruc/{ruc}` — consulta de RUC (SUNAT).
- `GET /api/dni/{dni}` — consulta de DNI (RENIEC).
- `GET /api/tipo-cambio/{date}` — tipo de cambio para una fecha (`YYYY-MM-DD`).
- `GET /api/tipo-cambio` — tipo de cambio del día actual.
- `GET /users` y `GET /users/{id}` — endpoints de ejemplo del skeleton original.

## Stack

- PHP 8.2 sobre [NGINX Unit](https://unit.nginx.org/) (`unit:php8.2`).
- [Slim Framework 4](https://www.slimframework.com/) + PHP-DI.
- [Illuminate Database (Eloquent)](https://laravel.com/docs/eloquent) sobre MySQL.
- Autenticación por token simple vía `SessionMiddleware` (`AUTH_TOKEN` en `.env`).

## Requisitos

- Docker y Docker Compose.
- Puertos libres en el host: `8080` (API) y `3307` (MySQL).

## Levantar el entorno local

El proyecto incluye un `docker-compose.yml` que levanta la API (usando el mismo `Dockerfile` de producción) junto con una base de datos MySQL para desarrollo.

```bash
docker compose up -d --build
```

Esto levanta:

- **app**: la API, accesible en `http://localhost:8080`.
- **mysql**: MySQL 8.0, accesible desde el host en `localhost:3307` (dentro de la red de Docker como `mysql:3306`).

El código fuente se monta como volumen (`.:/var/www/html`), por lo que los cambios se reflejan sin reconstruir la imagen. Solo hace falta reconstruir (`docker compose up -d --build`) cuando cambian `Dockerfile` o `composer.json`/`composer.lock`.

Para ver logs:

```bash
docker compose logs -f app
```

Para detener el entorno:

```bash
docker compose down
```

Para detener y borrar también los datos de MySQL:

```bash
docker compose down -v
```

### Variables de entorno

La app lee configuración de `.env` (vía `vlucas/phpdotenv`). El `docker-compose.yml` sobrescribe las variables de conexión a base de datos para apuntar al contenedor `mysql` (host `mysql`, puerto `3306`, base `api_gob`, usuario/clave `root`/`root`), sin necesidad de tocar el `.env` versionado. El resto de variables (`AUTH_TOKEN`, `EXTERNAL_API_URL`, `CACHE_TTL_DAYS`, `APP_TIMEZONE`) se toman del `.env`.

### Esquema de base de datos

El repo solo versiona el SQL de `sql/2026_08_30_ruc_padron_sync.sql` (tablas `ubigeo` y `ruc_padron_staging`). Las tablas usadas por `TipoCambioCache` (`tipo_cambio_cache`) y `ApiToken` (`api_tokens`) no tienen migración incluida, así que en una base de datos nueva (como la de este `docker-compose`) los endpoints que las usan (p. ej. `/api/tipo-cambio`) fallarán con un error 500 de "tabla no existe" hasta crearlas manualmente.

### Problemas de permisos (logs)

Como el código se monta desde el host, si el proceso dentro del contenedor no puede escribir en `logs/` o `var/`, ajusta permisos localmente:

```bash
chmod -R 777 logs var
```

## Testing

```bash
composer install
composer test
```

(`phpunit`, según `phpunit.xml`).

## Producción

En producción se usa únicamente el `Dockerfile` (sin `docker-compose`), construyendo la imagen con el código y `vendor/` incluidos, servida por NGINX Unit en el puerto `8081`.
