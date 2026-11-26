-- =============================================================================
-- ICTC: revierte una instalación completa del script 010 antes de usar los
-- permisos configurables. Ejecutar como ICTC en modo script.
-- Restablece el CHECK de PRUEBA_USUARIO_ROL y conserva sus asignaciones.
-- Elimina los roles, permisos e historial de las tres tablas nuevas.
-- Si 010 se interrumpió, revisar primero los objetos que quedaron creados.
-- =============================================================================

WHENEVER SQLERROR EXIT SQL.SQLCODE ROLLBACK

DECLARE
    v_eventos NUMBER;
    v_roles NUMBER;
    v_roles_iniciales NUMBER;
    v_permisos NUMBER;
    v_permisos_iniciales NUMBER;
    v_fk_vigente NUMBER;
BEGIN
    IF USER <> 'ICTC' OR SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') <> 'ICTC' THEN
        RAISE_APPLICATION_ERROR(-20001, 'Ejecutar desde una sesion del esquema ICTC.');
    END IF;

    SELECT COUNT(*)
      INTO v_fk_vigente
      FROM user_constraints
     WHERE table_name = 'PRUEBA_USUARIO_ROL'
       AND constraint_name = 'PRUEBA_USUARIO_ROL_ROL_FK'
       AND constraint_type = 'R'
       AND status = 'ENABLED'
       AND validated = 'VALIDATED';

    IF v_fk_vigente <> 1 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Se requiere una instalacion completa de 010.');
    END IF;

    SELECT COUNT(*) INTO v_eventos FROM ICTC.PRUEBA_SEGURIDAD_EVENTO;
    IF v_eventos > 0 THEN
        RAISE_APPLICATION_ERROR(-20003, 'Hay eventos de seguridad que deben conservarse.');
    END IF;

    SELECT COUNT(*), COUNT(CASE
               WHEN DESCRIPCION IS NULL AND (
                   (CODIGO = 'ADMINISTRADOR' AND NOMBRE = 'Administrador' AND ES_SISTEMA = 'S')
                   OR (CODIGO = 'GERENCIA' AND NOMBRE = 'Gerencia' AND ES_SISTEMA = 'N')
               ) THEN 1
           END)
      INTO v_roles, v_roles_iniciales
      FROM ICTC.PRUEBA_ROL;

    SELECT COUNT(*), COUNT(CASE
               WHEN ROL = 'ADMINISTRADOR' AND PERMISO IN (
                   'administracion.laboratorios', 'administracion.usuarios', 'administracion.roles',
                   'comisiones.ver', 'comisiones.procesar', 'comisiones.enviar'
               ) THEN 1
               WHEN ROL = 'GERENCIA' AND PERMISO IN (
                   'comisiones.ver', 'comisiones.aprobar', 'comisiones.rechazar'
               ) THEN 1
           END)
      INTO v_permisos, v_permisos_iniciales
      FROM ICTC.PRUEBA_ROL_PERMISO;

    IF v_roles <> 2 OR v_roles_iniciales <> 2 OR v_permisos <> 9 OR v_permisos_iniciales <> 9 THEN
        RAISE_APPLICATION_ERROR(-20004, 'Los roles o permisos difieren de la matriz inicial.');
    END IF;
END;
/

-- Validar el CHECK antes de retirar la FK. Si hay un rol distinto en alguna
-- asignación, Oracle rechaza el CHECK y el script se detiene antes de eliminar.
ALTER TABLE ICTC.PRUEBA_USUARIO_ROL
    ADD CONSTRAINT PRUEBA_USUARIO_ROL_CK CHECK (ROL IN ('ADMINISTRADOR', 'GERENCIA')) ENABLE VALIDATE;

ALTER TABLE ICTC.PRUEBA_USUARIO_ROL DROP CONSTRAINT PRUEBA_USUARIO_ROL_ROL_FK;

-- Oracle elimina el trigger y el índice al eliminar la tabla de eventos.
DROP TABLE ICTC.PRUEBA_SEGURIDAD_EVENTO;
DROP SEQUENCE ICTC.PRUEBA_SEGURIDAD_EVENTO_SEQ;
DROP TABLE ICTC.PRUEBA_ROL_PERMISO;
DROP TABLE ICTC.PRUEBA_ROL;
