-- La corrida del 2026-08-31 falló con:
--   SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'numero'
-- El padrón de SUNAT es texto libre de un dataset de décadas: no hay garantía de que
-- numero/interior/lote/dpto/manzana/kilometro/via_tipo/via_nombre/zona_codigo/zona_tipo
-- quepan siempre en varchar(20)/varchar(50)/varchar(100). Se ensanchan a varchar(255)
-- tanto en la tabla de staging como en ruc_cache (que recibe estos mismos valores via
-- upsert). Ejecutar contra la base de datos de producción antes de la próxima corrida.

ALTER TABLE `ruc_padron_staging`
  MODIFY COLUMN `via_tipo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `via_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `zona_codigo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `zona_tipo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `numero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `interior` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `lote` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `dpto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `manzana` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `kilometro` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;

ALTER TABLE `ruc_cache`
  MODIFY COLUMN `via_tipo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `via_nombre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `zona_codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `zona_tipo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `numero` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `interior` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `lote` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `dpto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `manzana` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `kilometro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;
