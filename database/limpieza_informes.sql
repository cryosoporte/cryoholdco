-- =============================================================================
-- Limpieza de datos del módulo Informes (Oracle 11g)
--
-- Revisa cada bloque antes de ejecutarlo. Los UPDATE/DDL de este archivo NO se han
-- ejecutado; las consultas SELECT sí se probaron en lectura contra el Oracle real.
-- Ejecuta con un usuario con permiso de escritura (la app usa uno de solo lectura
-- para este fin) y, de preferencia, en una ventana de poco uso.
-- =============================================================================


-- =============================================================================
-- 1. ENVÍO MARCADO EN UN SERVICIO Y NO EN EL OTRO  (Criocord ~402, Lazo ~107)
--
-- Regla: si algún contrato del mismo contrato base (cualquier servicio y estado) ya
-- tiene FLAG_ENVIO_CORREO = 1, los demás servicios del contrato base se marcan
-- igual, con la fecha de envío más reciente y el usuario de ese envío.
-- Es lo mismo que hace la aplicación al enviar y lo que ya asume el listado.
-- =============================================================================

-- 1.1 Vista previa: filas de LAB_COLECTAS que se modificarían, por empresa.
SELECT v.no_cia, COUNT(*) AS filas_a_marcar, COUNT(DISTINCT v.numero_base) AS contratos_base
FROM lab_colectas l
JOIN ven_contratos v ON v.no_cia = l.no_cia AND v.numero_contrato = l.numero_contrato
WHERE NVL(l.flag_envio_correo, 0) <> 1
  AND v.numero_base IS NOT NULL
  AND EXISTS (SELECT 1
              FROM ven_contratos v2
              JOIN lab_colectas l2 ON l2.no_cia = v2.no_cia AND l2.numero_contrato = v2.numero_contrato
              WHERE v2.no_cia = v.no_cia AND v2.numero_base = v.numero_base AND l2.flag_envio_correo = 1)
GROUP BY v.no_cia
ORDER BY v.no_cia;

-- 1.2 Respaldo de las filas que se van a modificar (conserva sus valores actuales).
--     Nota: CREATE TABLE confirma la transacción en curso.
CREATE TABLE bk_lab_colectas_envio_20261127 AS
SELECT l.*
FROM lab_colectas l
JOIN ven_contratos v ON v.no_cia = l.no_cia AND v.numero_contrato = l.numero_contrato
WHERE NVL(l.flag_envio_correo, 0) <> 1
  AND v.numero_base IS NOT NULL
  AND EXISTS (SELECT 1
              FROM ven_contratos v2
              JOIN lab_colectas l2 ON l2.no_cia = v2.no_cia AND l2.numero_contrato = v2.numero_contrato
              WHERE v2.no_cia = v.no_cia AND v2.numero_base = v.numero_base AND l2.flag_envio_correo = 1);

-- 1.3 La corrección. (La condición sobre FLAG_ENVIO_CORREO va en el WHERE del UPDATE
--     porque Oracle no permite actualizar columnas que participan en el ON.)
MERGE INTO lab_colectas l
USING (
    SELECT v.no_cia, v.numero_contrato, e.fecha_envio, e.usuario_envio
    FROM ven_contratos v
    JOIN (SELECT v2.no_cia,
                 v2.numero_base,
                 MAX(l2.envio_fecha_correo) AS fecha_envio,
                 MAX(l2.usuario_envio) KEEP (DENSE_RANK LAST ORDER BY l2.envio_fecha_correo) AS usuario_envio
          FROM ven_contratos v2
          JOIN lab_colectas l2 ON l2.no_cia = v2.no_cia AND l2.numero_contrato = v2.numero_contrato
          WHERE l2.flag_envio_correo = 1 AND v2.numero_base IS NOT NULL
          GROUP BY v2.no_cia, v2.numero_base) e
      ON e.no_cia = v.no_cia AND e.numero_base = v.numero_base
) s
ON (l.no_cia = s.no_cia AND l.numero_contrato = s.numero_contrato)
WHEN MATCHED THEN UPDATE
    SET l.flag_envio_correo = 1,
        l.envio_fecha_correo = s.fecha_envio,
        l.usuario_envio = s.usuario_envio
    WHERE NVL(l.flag_envio_correo, 0) <> 1;

-- 1.4 Verificación: debe devolver 0 filas (ningún contrato base con envío a medias).
--     Si el resultado es correcto ejecuta COMMIT; si no, ROLLBACK.
SELECT v.no_cia, v.numero_base
FROM ven_contratos v
JOIN lab_colectas l ON l.no_cia = v.no_cia AND l.numero_contrato = v.numero_contrato
WHERE v.numero_base IS NOT NULL
GROUP BY v.no_cia, v.numero_base
HAVING MAX(NVL(l.flag_envio_correo, 0)) = 1 AND MIN(NVL(l.flag_envio_correo, 0)) = 0;

-- COMMIT;
-- ROLLBACK;

-- 1.5 Si hubiera que deshacer DESPUÉS del COMMIT, restaura desde el respaldo:
-- UPDATE lab_colectas l
--    SET (l.flag_envio_correo, l.envio_fecha_correo, l.usuario_envio) =
--        (SELECT b.flag_envio_correo, b.envio_fecha_correo, b.usuario_envio
--           FROM bk_lab_colectas_envio_20261127 b
--          WHERE b.no_cia = l.no_cia AND b.numero_contrato = l.numero_contrato)
--  WHERE EXISTS (SELECT 1 FROM bk_lab_colectas_envio_20261127 b
--                 WHERE b.no_cia = l.no_cia AND b.numero_contrato = l.numero_contrato);
-- DROP TABLE bk_lab_colectas_envio_20261127;


-- =============================================================================
-- 2. DATOS QUE REQUIEREN DECISIÓN (no se corrigen con un UPDATE automático)
--
-- Lista, dentro del alcance de Informes (CON/DES, servicios S y T, con colecta y
-- FECHA_REGISTRO desde 2023), lo que hay que revisar, con la acción sugerida:
--
--   B  Tiene LIBERACION (APTO/NO APTO) pero NO tiene registro en LAB_COLECTAS_INFO.
--      Acción: laboratorio confirma si está liberado y se registra en INFO
--      (la app lo muestra como NO LIBERADO y bloquea el envío).
--   C  Tiene registro en INFO pero LIBERACION vacía en VEN_CONTRATOS.
--      Acción: completar LIBERACION con el resultado real. IMPORTANTE: hoy la app
--      lo muestra como NO APTO y pediría un motivo.
--   A  Contrato base ya enviado con un servicio sin registro en INFO.
--      Acción: revisar si se envió sin liberar o si falta el registro en INFO.
--   D  Sin FECHA_COLECTA (incluye contratos de prueba): no entra en los rangos de días.
--   E  No enviado y el cliente (titular 1) no tiene MAIL_CONTACTO.
--   E2 No enviado y el MAIL_CONTACTO tiene formato inválido.
-- =============================================================================

WITH alcance AS (
    SELECT c1.no_cia,
           c1.numero_base,
           c1.numero_contrato,
           c1.servicio,
           c1.liberacion,
           c3.fecha_colecta,
           c2.identificacion AS cliente,
           c2.mail_contacto,
           NVL(i.en_info, 0) AS en_info,
           NVL(e.base_enviada, 0) AS base_enviada
    FROM ven_contratos c1
    JOIN ven_clientes c2 ON c2.no_cia = c1.no_cia AND c2.identificacion = c1.identificacion1
    JOIN lab_colectas c3 ON c3.no_cia = c1.no_cia AND c3.numero_contrato = c1.numero_contrato
    LEFT JOIN (SELECT DISTINCT no_cia, numero_contrato, 1 AS en_info FROM lab_colectas_info) i
           ON i.no_cia = c1.no_cia AND i.numero_contrato = c1.numero_contrato
    LEFT JOIN (SELECT v.no_cia, v.numero_base, MAX(NVL(l.flag_envio_correo, 0)) AS base_enviada
                 FROM ven_contratos v
                 JOIN lab_colectas l ON l.no_cia = v.no_cia AND l.numero_contrato = v.numero_contrato
                WHERE v.numero_base IS NOT NULL
                GROUP BY v.no_cia, v.numero_base) e
           ON e.no_cia = c1.no_cia AND e.numero_base = c1.numero_base
    WHERE c1.estado_contrato IN ('CON', 'DES')
      AND c1.servicio IN ('S', 'T')
      AND c1.numero_base IS NOT NULL
      AND c3.fecha_registro >= DATE '2023-01-01'
)
SELECT 'B' AS categoria, no_cia, numero_base, numero_contrato, servicio, liberacion AS dato, 'Tiene LIBERACION pero no registro en INFO' AS problema
FROM alcance
WHERE en_info = 0 AND UPPER(TRIM(liberacion)) IN ('APTO', 'NO APTO')
UNION ALL
SELECT 'C', no_cia, numero_base, numero_contrato, servicio, NULL, 'Registro en INFO pero LIBERACION vacia'
FROM alcance
WHERE en_info > 0 AND TRIM(liberacion) IS NULL
UNION ALL
SELECT 'A', no_cia, numero_base, numero_contrato, servicio, liberacion, 'Contrato base enviado con un servicio sin registro en INFO'
FROM alcance
WHERE base_enviada = 1 AND en_info = 0
UNION ALL
SELECT 'D', no_cia, numero_base, numero_contrato, servicio, NULL, 'Sin fecha de colecta'
FROM alcance
WHERE fecha_colecta IS NULL
UNION ALL
SELECT 'E', no_cia, numero_base, numero_contrato, servicio, cliente, 'No enviado y sin MAIL_CONTACTO'
FROM alcance
WHERE base_enviada = 0 AND TRIM(mail_contacto) IS NULL
UNION ALL
SELECT 'E2', no_cia, numero_base, numero_contrato, servicio, mail_contacto, 'No enviado y MAIL_CONTACTO con formato invalido'
FROM alcance
WHERE base_enviada = 0
  AND TRIM(mail_contacto) IS NOT NULL
  AND NOT REGEXP_LIKE(TRIM(mail_contacto), '^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$')
ORDER BY 1, 2, 3, 4;
