-- =============================================================================
--  Verificacion. Todo aqui es SELECT: no cambia nada.
--  Correr despues de 001, 002 y 003.
--
--  Recordatorio: una fila por contrato BASE (sin el sufijo de servicio) y
--  solo para bases con servicio S o T. El historial no se carga.
-- =============================================================================


-- 1. La tabla y sus reglas quedaron puestas. Esperado: 5 constraints
--    (PK, 1 FK, 3 CHECK) mas las NOT NULL.
SELECT constraint_name, constraint_type, status
  FROM all_constraints
 WHERE owner = 'ICTC'
   AND table_name = 'VEN_CONTRATOS_TOMAS'
 ORDER BY constraint_type, constraint_name;


-- 2. El trigger esta vivo. Esperado: ENABLED y VALID.
SELECT t.trigger_name, t.status AS habilitado, o.status AS compilado
  FROM all_triggers t
  JOIN all_objects o
    ON o.owner = t.owner
   AND o.object_name = t.trigger_name
   AND o.object_type = 'TRIGGER'
 WHERE t.owner = 'ICTC'
   AND t.trigger_name = 'TR_VEN_CONTRATOS_TOMA_INI';


-- 3. RECONCILIACION — la consulta que importa con el tiempo.
--
--    Bases con servicio S o T, creadas desde que existe el trigger, que no
--    tienen su toma 1. Esperado: 0, siempre. Si sube, el trigger se quedo
--    sin correr y hay que mirar por que.
--
--    Cambia la fecha por la del dia en que instalaste el trigger.
SELECT COUNT(*) AS bases_nuevas_sin_toma
  FROM (
       SELECT DISTINCT
              v.NO_CIA,
              SUBSTR(v.NUMERO_CONTRATO, 1, INSTR(v.NUMERO_CONTRATO, '-', -1) - 1) AS BASE
         FROM ICTC.VEN_CONTRATOS v
        WHERE v.FECHA_CONTRATO >= TO_DATE('2026-11-05', 'YYYY-MM-DD')
          AND INSTR(v.NUMERO_CONTRATO, '-', -1) > 0
          AND SUBSTR(v.NUMERO_CONTRATO, INSTR(v.NUMERO_CONTRATO, '-', -1) + 1) IN ('S', 'T')
       ) b
 WHERE NOT EXISTS (
       SELECT 1
         FROM ICTC.VEN_CONTRATOS_TOMAS t
        WHERE t.NO_CIA = b.NO_CIA
          AND t.NUMERO_CONTRATO_BASE = b.BASE
          AND t.NUMERO_TOMA = 1
   );


-- 4. Lo contrario: tomas de bases que NO llevan serologia.
--    Si el trigger esta bien, esperado 0. Seria una base cuyos servicios
--    son todos P, A, N, G... y que aun asi tiene toma.
SELECT COUNT(*) AS tomas_de_bases_sin_st
  FROM ICTC.VEN_CONTRATOS_TOMAS t
 WHERE NOT EXISTS (
       SELECT 1
         FROM ICTC.VEN_CONTRATOS v
        WHERE v.NO_CIA = t.NO_CIA
          AND v.NUMERO_CONTRATO IN (t.NUMERO_CONTRATO_BASE || '-S',
                                    t.NUMERO_CONTRATO_BASE || '-T')
   );


-- 5. HUERFANAS — lo que vigila la FK que no se puede poner.
--    Tomas cuya base ya no tiene ningun contrato detras, sea porque se
--    inserto mal o porque borraron el contrato. Esperado: 0.
SELECT t.NO_CIA, t.NUMERO_CONTRATO_BASE, t.NUMERO_TOMA
  FROM ICTC.VEN_CONTRATOS_TOMAS t
 WHERE NOT EXISTS (
       SELECT 1
         FROM ICTC.VEN_CONTRATOS v
        WHERE v.NO_CIA = t.NO_CIA
          AND v.NUMERO_CONTRATO LIKE t.NUMERO_CONTRATO_BASE || '-_'
   );


-- 6. Como va quedando el estado, segun las dos fechas.
--    Recien instalado da una sola fila: "1 falta la prueba", toma 1.
SELECT CASE
           WHEN FECHA_RESULTADO IS NOT NULL THEN '3 examen recibido'
           WHEN FECHA_TOMA      IS NOT NULL THEN '2 prueba tomada'
           ELSE                                  '1 falta la prueba'
       END AS estado,
       NUMERO_TOMA,
       COUNT(*) AS filas
  FROM ICTC.VEN_CONTRATOS_TOMAS
 GROUP BY CASE
              WHEN FECHA_RESULTADO IS NOT NULL THEN '3 examen recibido'
              WHEN FECHA_TOMA      IS NOT NULL THEN '2 prueba tomada'
              ELSE                                  '1 falta la prueba'
          END,
          NUMERO_TOMA
 ORDER BY 1, 2;


-- 7. Nadie tiene una segunda toma sin tener la primera. Esperado: 0 filas.
SELECT t2.NO_CIA, t2.NUMERO_CONTRATO_BASE
  FROM ICTC.VEN_CONTRATOS_TOMAS t2
 WHERE t2.NUMERO_TOMA = 2
   AND NOT EXISTS (
       SELECT 1
         FROM ICTC.VEN_CONTRATOS_TOMAS t1
        WHERE t1.NO_CIA = t2.NO_CIA
          AND t1.NUMERO_CONTRATO_BASE = t2.NUMERO_CONTRATO_BASE
          AND t1.NUMERO_TOMA = 1
   );


-- 8. Prueba de humo del trigger, si quieres verlo funcionar de verdad.
--    Inserta los dos servicios de una misma base y comprueba que sale UNA
--    sola toma. Luego borra los contratos; la toma hay que borrarla aparte,
--    porque sin FK no hay CASCADE.
--
--    El INSERT va con las columnas justas: las otras tres NOT NULL de
--    VEN_CONTRATOS (COBRADOR, DEBITO_AUTOMATIC, TITULAR_PAGO) tienen DEFAULT.
--    La compania '001' existe. Hazlo fuera de horario, por si acaso.
--
-- INSERT INTO ICTC.VEN_CONTRATOS (NO_CIA, NUMERO_CONTRATO, FECHA_CONTRATO)
--      VALUES ('001', 'PE-CR-2026-999999-9-S', SYSDATE);
-- INSERT INTO ICTC.VEN_CONTRATOS (NO_CIA, NUMERO_CONTRATO, FECHA_CONTRATO)
--      VALUES ('001', 'PE-CR-2026-999999-9-T', SYSDATE);
-- INSERT INTO ICTC.VEN_CONTRATOS (NO_CIA, NUMERO_CONTRATO, FECHA_CONTRATO)
--      VALUES ('001', 'PE-CR-2026-999999-9-P', SYSDATE);
--
-- SELECT * FROM ICTC.VEN_CONTRATOS_TOMAS
--  WHERE NUMERO_CONTRATO_BASE = 'PE-CR-2026-999999-9';
--    -- debe traer EXACTAMENTE 1 fila, toma 1, todo lo demas en NULL
--
-- DELETE FROM ICTC.VEN_CONTRATOS_TOMAS
--  WHERE NUMERO_CONTRATO_BASE = 'PE-CR-2026-999999-9';
-- DELETE FROM ICTC.VEN_CONTRATOS
--  WHERE NUMERO_CONTRATO LIKE 'PE-CR-2026-999999-9-_';
-- COMMIT;


-- =============================================================================
--  Marcha atras, si hiciera falta. En este orden.
-- =============================================================================
-- DROP TRIGGER ICTC.TR_VEN_CONTRATOS_TOMA_INI;
-- DROP TABLE   ICTC.VEN_CONTRATOS_TOMAS CASCADE CONSTRAINTS;
