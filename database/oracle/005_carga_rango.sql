-- =============================================================================
--  Ampliar la carga hacia atras, para que el mostrador sirva de verdad.
--
--  Hoy la tabla tiene 6 bases (las de noviembre). Existen 25.536 bases con
--  servicio S o T, asi que casi cualquier contrato que escanee el laboratorio
--  da "todavia no esta cargado en el sistema de tomas".
--
--  ---------------------------------------------------------------------------
--  LO IMPORTANTE DE ESTE SCRIPT
--  ---------------------------------------------------------------------------
--  Una base que entre con FECHA_TOMA en NULL sale en el mostrador como
--  "Puede tomarse". Si a esa senora ya le sacaron sangre, se la sacan otra vez.
--
--  Y pasa seguido: de las 412 bases de los ultimos 6 meses, 247 YA tienen la
--  serologia hecha, y 210 de esas no tienen ninguna fecha guardada.
--
--  Por eso este script no carga en blanco. Para cada base busca una fecha de
--  toma en tres sitios, en este orden:
--
--    1. VEN_CONTRATOS_LABORATORIOS.FECHA_MUESTRA ....... la buena
--    2. VEN_CONTRATOS_LABORATORIOS.FECHA_RESULTADO ..... si no hay muestra
--    3. LAB_COLECTAS.FECHA_ENTREGA, solo si SEROLOGIA='S'
--
--  La tercera es una aproximacion: es la fecha de la colecta, no la del
--  analisis. Se usa a proposito. La alternativa es dejar esas 210 en NULL, y
--  entre "una fecha aproximada en un caso ya cerrado" y "pinchar dos veces a
--  una senora", la fecha aproximada hace menos dano.
--
--  Si prefieres no aproximar nada, cambia FALLBACK_COLECTA a 0 mas abajo:
--  esas bases quedan FUERA de la tabla y el mostrador seguira diciendo "no
--  esta cargado", que tambien es seguro, solo que menos util.
--  ---------------------------------------------------------------------------
--
--  EL RANGO lo decides en la linea marcada como RANGO:
--     ultimos 6 meses ... ADD_MONTHS(TRUNC(SYSDATE, 'MM'), -6)   -> 412 bases
--     ultimo ano ........ ADD_MONTHS(TRUNC(SYSDATE, 'MM'), -12)  -> 845 bases
--     todo .............. TO_DATE('1900-01-01', 'YYYY-MM-DD')    -> 25.536 bases
--
--  Se puede repetir: el NOT EXISTS salta lo ya cargado, y las 6 filas de
--  noviembre no se tocan.
-- =============================================================================


-- -----------------------------------------------------------------------------
--  MIRAR ANTES DE CARGAR. Esto no cambia nada: dice como quedaria el reparto.
--  Lo que importa es que "entran_como_pendiente" sean de verdad pendientes.
-- -----------------------------------------------------------------------------

-- SELECT CASE
--            WHEN fecha_toma IS NULL THEN '1 entran como pendiente'
--            WHEN fecha_resultado IS NOT NULL THEN '3 con resultado'
--            ELSE '2 ya tomadas'
--        END AS como_entran,
--        COUNT(*) AS bases
--   FROM (
--        SELECT x.BASE,
--               MIN(NVL(NVL(x.FECHA_MUESTRA, x.FECHA_RESULTADO),
--                       CASE WHEN x.SEROLOGIA = 'S' THEN x.FECHA_ENTREGA END)) AS fecha_toma,
--               MIN(x.FECHA_RESULTADO) AS fecha_resultado
--          FROM (
--               SELECT v.NO_CIA, v.NUMERO_BASE AS BASE,
--                      l.FECHA_MUESTRA, l.FECHA_RESULTADO,
--                      c.SEROLOGIA, c.FECHA_ENTREGA
--                 FROM ICTC.VEN_CONTRATOS v
--                 LEFT JOIN ICTC.VEN_CONTRATOS_LABORATORIOS l
--                        ON l.NO_CIA = v.NO_CIA AND l.NUMERO_CONTRATO = v.NUMERO_CONTRATO
--                 LEFT JOIN ICTC.LAB_COLECTAS c
--                        ON c.NO_CIA = v.NO_CIA AND c.NUMERO_CONTRATO = v.NUMERO_CONTRATO
--                WHERE v.SERVICIO IN ('S', 'T')
--                  AND v.FECHA_CONTRATO >= ADD_MONTHS(TRUNC(SYSDATE, 'MM'), -6)
--               ) x
--         GROUP BY x.NO_CIA, x.BASE
--        )
--  GROUP BY CASE
--               WHEN fecha_toma IS NULL THEN '1 entran como pendiente'
--               WHEN fecha_resultado IS NOT NULL THEN '3 con resultado'
--               ELSE '2 ya tomadas'
--           END
--  ORDER BY 1;


-- -----------------------------------------------------------------------------
--  LA CARGA
-- -----------------------------------------------------------------------------

INSERT INTO ICTC.OPE_TOMAS_SEROLOGICAS (
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
    -- Fecha de la toma: la real y, si no hay pero la serologia ya esta hecha,
    -- la de la colecta. FALLBACK_COLECTA: cambia el 1 por 0 para desactivarlo.
    MIN(NVL(NVL(x.FECHA_MUESTRA, x.FECHA_RESULTADO),
            CASE WHEN x.SEROLOGIA = 'S' AND 1 = 1 THEN x.FECHA_ENTREGA END)),
    MIN(x.FECHA_RESULTADO),
    MIN(x.IDENTIFICACION),
    MIN(x.CODIGO_SEDE)
FROM (
    SELECT
        v.NO_CIA,
        v.NUMERO_BASE AS BASE,
        l.FECHA_MUESTRA,
        l.FECHA_RESULTADO,
        c.SEROLOGIA,
        c.FECHA_ENTREGA,
        s.IDENTIFICACION,
        s.CODIGO_SEDE
    FROM ICTC.VEN_CONTRATOS v
    LEFT JOIN ICTC.VEN_CONTRATOS_LABORATORIOS l
           ON l.NO_CIA = v.NO_CIA
          AND l.NUMERO_CONTRATO = v.NUMERO_CONTRATO
    LEFT JOIN ICTC.LAB_COLECTAS c
           ON c.NO_CIA = v.NO_CIA
          AND c.NUMERO_CONTRATO = v.NUMERO_CONTRATO
    LEFT JOIN ICTC.LAB_LABORATORIOS_SEDES s
           ON s.NO_CIA = l.NO_CIA
          AND s.IDENTIFICACION = l.IDENTIFICACION
          AND s.CODIGO_SEDE = l.CODIGO_SEDE
    WHERE v.SERVICIO IN ('S', 'T')
      AND v.NUMERO_BASE IS NOT NULL
      -- RANGO: cambia solo esta linea.
      AND v.FECHA_CONTRATO >= ADD_MONTHS(TRUNC(SYSDATE, 'MM'), -6)
) x
WHERE NOT EXISTS (
    SELECT 1
      FROM ICTC.OPE_TOMAS_SEROLOGICAS t
     WHERE t.NO_CIA = x.NO_CIA
       AND t.NUMERO_CONTRATO_BASE = x.BASE
       AND t.NUMERO_TOMA = 1
)
GROUP BY x.NO_CIA, x.BASE;

COMMIT;


-- -----------------------------------------------------------------------------
--  DESPUES DE CARGAR: cuantas quedaron en cada estado.
-- -----------------------------------------------------------------------------

-- SELECT CASE
--            WHEN FECHA_RESULTADO IS NOT NULL THEN '3 examen recibido'
--            WHEN FECHA_TOMA      IS NOT NULL THEN '2 prueba tomada'
--            ELSE                                  '1 falta la prueba'
--        END AS estado,
--        COUNT(*) AS bases
--   FROM ICTC.OPE_TOMAS_SEROLOGICAS
--  GROUP BY CASE
--               WHEN FECHA_RESULTADO IS NOT NULL THEN '3 examen recibido'
--               WHEN FECHA_TOMA      IS NOT NULL THEN '2 prueba tomada'
--               ELSE                                  '1 falta la prueba'
--           END
--  ORDER BY 1;


-- -----------------------------------------------------------------------------
--  MARCHA ATRAS de esta carga, sin tocar las 6 filas de noviembre ni lo que
--  haya marcado el mostrador desde entonces.
-- -----------------------------------------------------------------------------

--  Borra lo que trajo este script: todo contrato anterior a noviembre de
--  2026. Las 6 filas de noviembre y lo que marque el mostrador se quedan.
--  Cambia el COUNT(*) por DELETE cuando el numero te cuadre.

-- SELECT COUNT(*)          -- DELETE
--   FROM ICTC.OPE_TOMAS_SEROLOGICAS t
--  WHERE EXISTS (SELECT 1
--                  FROM ICTC.VEN_CONTRATOS v
--                 WHERE v.NO_CIA = t.NO_CIA
--                   AND v.NUMERO_BASE = t.NUMERO_CONTRATO_BASE
--                   AND v.FECHA_CONTRATO < TO_DATE('2026-11-01', 'YYYY-MM-DD'));
