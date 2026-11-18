-- =============================================================================
-- ICTC: verificacion de las fuentes de Reportes en produccion.
-- Ejecutar despues del DDL y repetir tras la primera importacion.
-- =============================================================================

SELECT SYS_CONTEXT('USERENV', 'DB_NAME') AS base,
       SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') AS esquema
  FROM dual;

-- Esperado: 6 columnas en la maestra, 46 en facturas y 12 en la bitacora.
SELECT table_name, COUNT(*) AS columnas
  FROM all_tab_columns
 WHERE owner = 'ICTC'
   AND table_name IN ('FUENTES_DATOS', 'FUENTE_FACTURAS_ORACLE', 'FUENTES_CARGAS')
 GROUP BY table_name
 ORDER BY table_name;

-- Esperado: tres tablas, cinco indices, una secuencia y un trigger validos.
SELECT object_name, object_type, status
  FROM all_objects
 WHERE owner = 'ICTC'
   AND object_name IN ('FUENTES_DATOS', 'FUENTE_FACTURAS_ORACLE',
                       'FUENTES_CARGAS', 'FUENTES_DATOS_PK',
                       'FFO_EMISION_IX', 'FFO_ID_INTERNO_IX',
                       'FUENTES_CARGAS_PK', 'FC_FUENTE_IX',
                       'FUENTES_CARGAS_SEQ', 'FUENTES_CARGAS_BI')
 ORDER BY object_type, object_name;

SELECT index_name, table_name, column_position, column_name
  FROM all_ind_columns
 WHERE index_owner = 'ICTC'
   AND index_name IN ('FUENTES_DATOS_PK', 'FUENTES_CARGAS_PK',
                      'FFO_EMISION_IX', 'FFO_ID_INTERNO_IX', 'FC_FUENTE_IX')
 ORDER BY index_name, column_position;

-- Las dos PK, el CHECK y la FK deben estar ENABLED y VALIDATED.
SELECT c.constraint_name, c.constraint_type, c.status, c.validated,
       r.table_name AS tabla_referida
  FROM all_constraints c
  LEFT JOIN all_constraints r
    ON r.owner = c.r_owner AND r.constraint_name = c.r_constraint_name
 WHERE c.owner = 'ICTC'
   AND c.constraint_name IN ('FUENTES_DATOS_PK', 'FUENTES_CARGAS_PK',
                             'FC_ESTADO_CK', 'FC_FUENTE_FK')
 ORDER BY c.constraint_name;

SELECT increment_by, cache_size, last_number, cycle_flag
  FROM all_sequences
 WHERE sequence_owner = 'ICTC'
   AND sequence_name = 'FUENTES_CARGAS_SEQ';

SELECT table_name, status, trigger_type, triggering_event, when_clause
  FROM all_triggers
 WHERE owner = 'ICTC'
   AND trigger_name = 'FUENTES_CARGAS_BI';

-- Esperado: ninguna fila.
SELECT line, position, text
  FROM all_errors
 WHERE owner = 'ICTC'
   AND type = 'TRIGGER'
   AND name = 'FUENTES_CARGAS_BI'
 ORDER BY sequence;

-- Antes de cargar: fecha y bitacora nulas, cero filas de facturas.
-- Despues: estado C, fecha presente y los tres conteos iguales.
WITH ultima_carga AS (
    SELECT id, codigo_fuente, estado, filas, ruta_archivo
      FROM ICTC.FUENTES_CARGAS
     WHERE id = (
         SELECT MAX(id)
           FROM ICTC.FUENTES_CARGAS
          WHERE codigo_fuente = 'FACTURAS_ORACLE'
     )
)
SELECT m.codigo, m.fecha_actualizacion, m.filas AS filas_maestra,
       c.id AS carga_id, c.estado, c.filas AS filas_bitacora,
       (SELECT COUNT(*) FROM ICTC.FUENTE_FACTURAS_ORACLE) AS filas_facturas,
       CASE
           WHEN c.id IS NULL THEN NULL
           WHEN c.ruta_archivo IS NULL THEN 'SI'
           ELSE 'NO'
       END AS temporal_eliminado
  FROM ICTC.FUENTES_DATOS m
  LEFT JOIN ultima_carga c ON c.codigo_fuente = m.codigo
 WHERE m.codigo = 'FACTURAS_ORACLE';
