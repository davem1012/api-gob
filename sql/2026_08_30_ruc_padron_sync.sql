-- Esquema para la sincronización diaria del padrón reducido de RUC de SUNAT.
-- Ejecutar manualmente contra la base de datos antes de desplegar bin/sync_ruc_padron.php.

-- 1. Catálogo de ubigeo (código SUNAT -> distrito/provincia/departamento).
--    Se llena una sola vez con bin/seed_ubigeo.php, no forma parte del sync diario.
CREATE TABLE `ubigeo` (
  `codigo` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `distrito` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provincia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `departamento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de staging: destino del bulk load diario del padrón.
--    Se trunca y recarga por completo en cada corrida; el tráfico en vivo nunca la toca.
CREATE TABLE `ruc_padron_staging` (
  `numero_documento` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` text COLLATE utf8mb4_unicode_ci,
  `estado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condicion` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` text COLLATE utf8mb4_unicode_ci,
  `ubigeo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `via_tipo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `via_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zona_codigo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zona_tipo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interior` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lote` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dpto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manzana` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kilometro` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distrito` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provincia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `departamento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `row_hash` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`numero_documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Columna para detectar cambios sin reescribir toda la tabla en cada corrida.
ALTER TABLE `ruc_cache`
  ADD COLUMN `row_hash` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `locales_anexos`;

-- 4. Trazabilidad de origen en dni_cache: distingue datos verificados por RENIEC
--    (vía el API externo) de datos inferidos del RUC10 del padrón de SUNAT
--    (nombre partido con una heurística, sin verificar contra RENIEC).
--    El default 'reniec' etiqueta correctamente, sin backfill aparte, todo lo
--    que ya existe hoy en la tabla (todo vino del API real hasta ahora).
ALTER TABLE `dni_cache`
  ADD COLUMN `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reniec' AFTER `full_name`;
