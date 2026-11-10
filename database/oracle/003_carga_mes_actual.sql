-- =============================================================================
--  Carga de los contratos base del mes en curso.
--
--  El trigger de 002_ cubre de su instalacion en adelante. Este script tapa
--  el hueco hacia atras: las bases que ya se firmaron este mes y que el
--  trigger no llego a ver.
--
--  Correr DESPUES del trigger, no antes. Asi no queda ninguna ventana: el
--  trigger se ocupa de lo que entre desde ya, y esto de lo anterior del mes.
--
--  Se puede repetir: el NOT EXISTS salta lo que ya esta cargado.
--
--  Al 2026-11-05 el mes tiene 17 contratos, que son 6 bases, las 6 con
--  servicio S y T. Entran 6 filas, no 17. Ninguna tiene todavia datos en
--  VEN_CONTRATOS_LABORATORIOS, asi que las 6 quedan como "falta tomarte la
--  prueba", que es lo que el trigger habria hecho.
-- =============================================================================


-- -----------------------------------------------------------------------------
--  Antes de insertar, mirar que va a entrar. Esto no cambia nada.
-- -----------------------------------------------------------------------------

-- SELECT v.NO_CIA,
--        SUBSTR(v.NUMERO_CONTRATO, 1, INSTR(v.NUMERO_CONTRATO, '-', -1) - 1) AS base,
--        COUNT(*)                   AS servicios_st,
--        MIN(v.FECHA_CONTRATO)      AS fecha
--   FROM ICTC.VEN_CONTRATOS v
--  WHERE v.FECHA_CONTRATO >= TRUNC(SYSDATE, 'MM')
--    AND INSTR(v.NUMERO_CONTRATO, '-', -1) > 0
--    AND SUBSTR(v.NUMERO_CONTRATO, INSTR(v.NUMERO_CONTRATO, '-', -1) + 1) IN ('S', 'T')
--  GROUP BY v.NO_CIA,
--           SUBSTR(v.NUMERO_CONTRATO, 1, INSTR(v.NUMERO_CONTRATO, '-', -1) - 1)
--  ORDER BY 4, 2;


-- -----------------------------------------------------------------------------
--  La carga.
--
--  Tres cosas que hace el SELECT, en orden:
--
--    1. Se queda solo con los servicios S y T; los demas (P, A, N, G...) no
--       llevan serologia.
--    2. Agrupa por base, para que los tres contratos de una misma persona
--       den UNA sola toma.
--    3. Trae de VEN_CONTRATOS_LABORATORIOS lo que haya para cualquiera de
--       los servicios de esa base. Hoy no hay nada, pero si corres esto el
--       dia 25 y para entonces alguna ya tiene su muestra, entra con fechas
--       en vez de entrar en blanco.
--
--  MIN() sobre las fechas y el laboratorio: si el S y el T trajeran datos
--  distintos (no deberia pasar, es la misma extraccion de sangre) se queda
--  con el primero y no duplica la toma.
--
--  Igual que siempre, dos cuidados con la fuente:
--    - FECHA_TOMA = NVL(FECHA_MUESTRA, FECHA_RESULTADO), porque hay filas con
--      resultado y sin fecha de muestra, y CK_VCT_ORDEN_FECHAS las rechaza.
--    - Laboratorio y sede solo si el par existe en LAB_LABORATORIOS_SEDES; si
--      no, entran en NULL y la toma no se pierde.
--
--  TRUNC(SYSDATE, 'MM') es el dia 1 del mes en curso. Para otro rango cambia
--  solo esa linea:
--      mes pasado tambien ... ADD_MONTHS(TRUNC(SYSDATE, 'MM'), -1)
--      desde una fecha ...... TO_DATE('2026-10-01', 'YYYY-MM-DD')
-- -----------------------------------------------------------------------------

INSERT INTO ICTC.VEN_CONTRATOS_TOMAS (
    NO_CIA,
    NUMERO_CONTRATO_BASE,
    NUMERO_TOMA,
    FECHA_TOMA,
    FECHA_RESULTADO,
    LABORATORIO_TOMA,
    SEDE_TOMA
)
SELECT
    x.NO_CIA,
    x.BASE,
    1,
    MIN(NVL(x.FECHA_MUESTRA, x.FECHA_RESULTADO)),
    MIN(x.FECHA_RESULTADO),
    MIN(x.IDENTIFICACION),
    MIN(x.CODIGO_SEDE)
FROM (
    SELECT
        v.NO_CIA,
        SUBSTR(v.NUMERO_CONTRATO, 1, INSTR(v.NUMERO_CONTRATO, '-', -1) - 1) AS BASE,
        l.FECHA_MUESTRA,
        l.FECHA_RESULTADO,
        s.IDENTIFICACION,
        s.CODIGO_SEDE
    FROM ICTC.VEN_CONTRATOS v
    LEFT JOIN ICTC.VEN_CONTRATOS_LABORATORIOS l
           ON l.NO_CIA = v.NO_CIA
          AND l.NUMERO_CONTRATO = v.NUMERO_CONTRATO
    LEFT JOIN ICTC.LAB_LABORATORIOS_SEDES s
           ON s.NO_CIA = l.NO_CIA
          AND s.IDENTIFICACION = l.IDENTIFICACION
          AND s.CODIGO_SEDE = l.CODIGO_SEDE
    WHERE v.FECHA_CONTRATO >= TRUNC(SYSDATE, 'MM')
      AND INSTR(v.NUMERO_CONTRATO, '-', -1) > 0
      AND SUBSTR(v.NUMERO_CONTRATO, INSTR(v.NUMERO_CONTRATO, '-', -1) + 1) IN ('S', 'T')
) x
WHERE NOT EXISTS (
    SELECT 1
      FROM ICTC.VEN_CONTRATOS_TOMAS t
     WHERE t.NO_CIA = x.NO_CIA
       AND t.NUMERO_CONTRATO_BASE = x.BASE
       AND t.NUMERO_TOMA = 1
)
GROUP BY x.NO_CIA, x.BASE;

COMMIT;


-- -----------------------------------------------------------------------------
--  Comprobacion: no debe quedar ninguna base del mes con S o T sin su toma 1.
--  Esperado: 0.
-- -----------------------------------------------------------------------------

-- SELECT COUNT(*) AS bases_del_mes_sin_toma
--   FROM (
--        SELECT DISTINCT
--               v.NO_CIA,
--               SUBSTR(v.NUMERO_CONTRATO, 1, INSTR(v.NUMERO_CONTRATO, '-', -1) - 1) AS BASE
--          FROM ICTC.VEN_CONTRATOS v
--         WHERE v.FECHA_CONTRATO >= TRUNC(SYSDATE, 'MM')
--           AND INSTR(v.NUMERO_CONTRATO, '-', -1) > 0
--           AND SUBSTR(v.NUMERO_CONTRATO, INSTR(v.NUMERO_CONTRATO, '-', -1) + 1) IN ('S', 'T')
--        ) b
--  WHERE NOT EXISTS (
--        SELECT 1
--          FROM ICTC.VEN_CONTRATOS_TOMAS t
--         WHERE t.NO_CIA = b.NO_CIA
--           AND t.NUMERO_CONTRATO_BASE = b.BASE
--           AND t.NUMERO_TOMA = 1
--    );
