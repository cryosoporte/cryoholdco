-- =============================================================================
-- ICTC: roles, permisos e historial para las tablas PRUEBA_.
-- Ejecutar como ICTC en modo script. PRUEBA_USUARIO_ROL conserva sus filas.
-- El DDL de Oracle confirma cambios implícitamente. Si se detiene por un error,
-- revisar los objetos creados antes de reintentar. Verificación: script 011.
-- =============================================================================

WHENEVER SQLERROR EXIT SQL.SQLCODE ROLLBACK

DECLARE
    v_existentes NUMBER;
    v_check_vigente NUMBER;
BEGIN
    IF USER <> 'ICTC' OR SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') <> 'ICTC' THEN
        RAISE_APPLICATION_ERROR(-20001, 'Ejecutar desde una sesion del esquema ICTC.');
    END IF;

    SELECT COUNT(*)
      INTO v_existentes
      FROM user_objects
     WHERE object_name IN ('PRUEBA_ROL', 'PRUEBA_ROL_PERMISO', 'PRUEBA_SEGURIDAD_EVENTO',
                           'PRUEBA_SEGURIDAD_EVENTO_SEQ', 'PRUEBA_SEGURIDAD_EVENTO_BI',
                           'PRUEBA_SEG_EVENTO_FECHA_IX');

    IF v_existentes > 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Ya existen objetos de roles y permisos PRUEBA_.');
    END IF;

    SELECT COUNT(*)
      INTO v_check_vigente
      FROM user_constraints
     WHERE table_name = 'PRUEBA_USUARIO_ROL'
       AND constraint_name = 'PRUEBA_USUARIO_ROL_CK'
       AND constraint_type = 'C'
       AND status = 'ENABLED'
       AND validated = 'VALIDATED';

    IF v_check_vigente <> 1 THEN
        RAISE_APPLICATION_ERROR(-20003, 'Se requiere el CHECK vigente de PRUEBA_USUARIO_ROL.');
    END IF;
END;
/

-- -----------------------------------------------------------------------------
-- Roles asignables. CODIGO es la clave permanente. El nombre se puede editar.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_ROL (
    CODIGO          VARCHAR2(30 BYTE)  NOT NULL,
    NOMBRE          VARCHAR2(60 CHAR)  NOT NULL,
    DESCRIPCION     VARCHAR2(300 CHAR),
    ES_SISTEMA      CHAR(1 BYTE)       DEFAULT 'N' NOT NULL,
    FECHA_CREACION  DATE               DEFAULT SYSDATE NOT NULL,
    CONSTRAINT PRUEBA_ROL_PK PRIMARY KEY (CODIGO),
    CONSTRAINT PRUEBA_ROL_NOMBRE_UK UNIQUE (NOMBRE),
    CONSTRAINT PRUEBA_ROL_SISTEMA_CK CHECK (ES_SISTEMA IN ('S', 'N'))
);

COMMENT ON TABLE ICTC.PRUEBA_ROL IS 'Roles asignables en el entorno de prueba.';

-- -----------------------------------------------------------------------------
-- Permisos de cada rol. Los códigos de permiso pertenecen al catálogo en código.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_ROL_PERMISO (
    ROL      VARCHAR2(30 BYTE)  NOT NULL,
    PERMISO  VARCHAR2(60 BYTE)  NOT NULL,
    CONSTRAINT PRUEBA_ROL_PERMISO_PK PRIMARY KEY (ROL, PERMISO),
    CONSTRAINT PRUEBA_ROL_PERMISO_ROL_FK FOREIGN KEY (ROL) REFERENCES ICTC.PRUEBA_ROL (CODIGO)
);

COMMENT ON TABLE ICTC.PRUEBA_ROL_PERMISO IS 'Permisos asignados a los roles de prueba.';

-- -----------------------------------------------------------------------------
-- Historial de seguridad. ROL conserva el código incluso al eliminar el rol.
-- USUARIOS contiene los IDs separados por comas y DETALLE guarda el JSON.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_SEGURIDAD_EVENTO (
    ID             NUMBER(10)          NOT NULL,
    ACCION         VARCHAR2(30 BYTE)   NOT NULL,
    ROL            VARCHAR2(30 BYTE),
    USUARIOS       VARCHAR2(200 BYTE),
    DETALLE        VARCHAR2(4000 BYTE),
    AUTOR_ID       NUMBER(10)          NOT NULL,
    AUTOR_NOMBRE   VARCHAR2(255 CHAR)  NOT NULL,
    AUTOR_EMPRESA  VARCHAR2(20 BYTE),
    FECHA          DATE                NOT NULL,
    CONSTRAINT PRUEBA_SEGURIDAD_EVENTO_PK PRIMARY KEY (ID),
    CONSTRAINT PRUEBA_SEG_EVENTO_ACCION_CK
        CHECK (ACCION IN ('ROL_CREADO', 'ROL_EDITADO', 'ROL_ELIMINADO', 'PERMISOS_ROL', 'ROLES_USUARIO'))
);

COMMENT ON TABLE ICTC.PRUEBA_SEGURIDAD_EVENTO IS 'Historial de cambios de roles, permisos y asignaciones de prueba.';

CREATE INDEX ICTC.PRUEBA_SEG_EVENTO_FECHA_IX ON ICTC.PRUEBA_SEGURIDAD_EVENTO (FECHA);

CREATE SEQUENCE ICTC.PRUEBA_SEGURIDAD_EVENTO_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;

CREATE TRIGGER ICTC.PRUEBA_SEGURIDAD_EVENTO_BI
BEFORE INSERT ON ICTC.PRUEBA_SEGURIDAD_EVENTO
FOR EACH ROW
WHEN (NEW.ID IS NULL)
BEGIN
    SELECT ICTC.PRUEBA_SEGURIDAD_EVENTO_SEQ.NEXTVAL INTO :NEW.ID FROM DUAL;
END;
/

DECLARE
    v_trigger_valido NUMBER;
BEGIN
    SELECT COUNT(*)
      INTO v_trigger_valido
      FROM user_objects
     WHERE object_name = 'PRUEBA_SEGURIDAD_EVENTO_BI'
       AND object_type = 'TRIGGER'
       AND status = 'VALID';

    IF v_trigger_valido <> 1 THEN
        RAISE_APPLICATION_ERROR(-20004, 'Revisar USER_ERRORS para PRUEBA_SEGURIDAD_EVENTO_BI.');
    END IF;
END;
/

-- -----------------------------------------------------------------------------
-- Matriz inicial. Conserva los permisos vigentes de Administración y Comisiones.
-- -----------------------------------------------------------------------------
INSERT INTO ICTC.PRUEBA_ROL (CODIGO, NOMBRE, ES_SISTEMA)
VALUES ('ADMINISTRADOR', 'Administrador', 'S');

INSERT INTO ICTC.PRUEBA_ROL (CODIGO, NOMBRE, ES_SISTEMA)
VALUES ('GERENCIA', 'Gerencia', 'N');

INSERT ALL
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'administracion.laboratorios')
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'administracion.usuarios')
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'administracion.roles')
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'comisiones.ver')
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'comisiones.procesar')
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'comisiones.enviar')
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('GERENCIA', 'comisiones.ver')
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('GERENCIA', 'comisiones.aprobar')
    INTO ICTC.PRUEBA_ROL_PERMISO (ROL, PERMISO) VALUES ('GERENCIA', 'comisiones.rechazar')
SELECT 1 FROM DUAL;

COMMIT;

-- Validar la FK con las filas existentes antes de retirar el CHECK.
ALTER TABLE ICTC.PRUEBA_USUARIO_ROL
    ADD CONSTRAINT PRUEBA_USUARIO_ROL_ROL_FK
    FOREIGN KEY (ROL) REFERENCES ICTC.PRUEBA_ROL (CODIGO) ENABLE VALIDATE;

ALTER TABLE ICTC.PRUEBA_USUARIO_ROL DROP CONSTRAINT PRUEBA_USUARIO_ROL_CK;
