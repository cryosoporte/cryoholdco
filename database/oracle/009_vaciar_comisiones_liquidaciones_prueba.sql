-- =============================================================================
-- ICTC: vacía las liquidaciones DE PRUEBA para empezar de cero.
-- Borra solo datos de las tablas PRUEBA_COM_LIQ*; no toca la estructura, los
-- roles (PRUEBA_USUARIO_ROL), la fuente de facturas ni ninguna tabla real.
-- Las secuencias siguen desde su último valor (los ID no se reinician).
-- =============================================================================

WHENEVER SQLERROR EXIT SQL.SQLCODE ROLLBACK

BEGIN
    IF USER <> 'ICTC' OR SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') <> 'ICTC' THEN
        RAISE_APPLICATION_ERROR(-20001, 'Ejecutar desde una sesion del esquema ICTC.');
    END IF;
END;
/

DELETE FROM ICTC.PRUEBA_COM_LIQ_ENVIO;
DELETE FROM ICTC.PRUEBA_COM_LIQ_EVENTO;
DELETE FROM ICTC.PRUEBA_COM_LIQ_CONTRATO;
DELETE FROM ICTC.PRUEBA_COM_LIQ_DETALLE;
DELETE FROM ICTC.PRUEBA_COM_LIQ;

COMMIT;

-- Verificación: todo debe quedar en 0.
SELECT (SELECT COUNT(*) FROM ICTC.PRUEBA_COM_LIQ) AS liquidaciones,
       (SELECT COUNT(*) FROM ICTC.PRUEBA_COM_LIQ_DETALLE) AS detalle,
       (SELECT COUNT(*) FROM ICTC.PRUEBA_COM_LIQ_CONTRATO) AS contratos,
       (SELECT COUNT(*) FROM ICTC.PRUEBA_COM_LIQ_EVENTO) AS eventos,
       (SELECT COUNT(*) FROM ICTC.PRUEBA_COM_LIQ_ENVIO) AS envios
  FROM dual;
