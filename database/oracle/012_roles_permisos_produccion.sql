-- =============================================================================
-- ICTC: roles, permisos e historial para producción.
-- Preparado para una futura ventana de despliegue, en modo script como ICTC.
-- El DDL de Oracle confirma cambios implícitamente. Si se detiene por un error,
-- revisar los objetos creados antes de reintentar.
-- =============================================================================

WHENEVER SQLERROR EXIT SQL.SQLCODE ROLLBACK

DECLARE
    v_objetos_existentes NUMBER;
    v_restricciones_existentes NUMBER;
BEGIN
    IF USER <> 'ICTC' OR SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') <> 'ICTC' THEN
        RAISE_APPLICATION_ERROR(-20001, 'Ejecutar desde una sesion del esquema ICTC.');
    END IF;

    SELECT COUNT(*)
      INTO v_objetos_existentes
      FROM user_objects
     WHERE object_name IN ('ROL', 'ROL_PERMISO', 'USUARIO_ROL', 'SEGURIDAD_EVENTO',
                           'ROL_PK', 'ROL_NOMBRE_UK', 'ROL_PERMISO_PK', 'USUARIO_ROL_PK',
                           'SEGURIDAD_EVENTO_PK', 'SEG_EVENTO_FECHA_IX',
                           'SEGURIDAD_EVENTO_SEQ', 'SEGURIDAD_EVENTO_BI');

    SELECT COUNT(*)
      INTO v_restricciones_existentes
      FROM user_constraints
     WHERE constraint_name IN ('ROL_PK', 'ROL_NOMBRE_UK', 'ROL_SISTEMA_CK',
                               'ROL_PERMISO_PK', 'ROL_PERMISO_ROL_FK',
                               'USUARIO_ROL_PK', 'USUARIO_ROL_ROL_FK',
                               'SEGURIDAD_EVENTO_PK', 'SEG_EVENTO_ACCION_CK');

    IF v_objetos_existentes > 0 OR v_restricciones_existentes > 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Ya existen objetos o restricciones de roles y permisos.');
    END IF;
END;
/

-- -----------------------------------------------------------------------------
-- Roles asignables. CODIGO es la clave permanente. El nombre se puede editar.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.ROL (
    CODIGO          VARCHAR2(30 BYTE)  NOT NULL,
    NOMBRE          VARCHAR2(60 CHAR)  NOT NULL,
    DESCRIPCION     VARCHAR2(300 CHAR),
    ES_SISTEMA      CHAR(1 BYTE)       DEFAULT 'N' NOT NULL,
    FECHA_CREACION  DATE               DEFAULT SYSDATE NOT NULL,
    CONSTRAINT ROL_PK PRIMARY KEY (CODIGO),
    CONSTRAINT ROL_NOMBRE_UK UNIQUE (NOMBRE),
    CONSTRAINT ROL_SISTEMA_CK CHECK (ES_SISTEMA IN ('S', 'N'))
);

COMMENT ON TABLE ICTC.ROL IS 'Roles asignables.';

-- -----------------------------------------------------------------------------
-- Permisos de cada rol. Los códigos pertenecen al catálogo de la aplicación.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.ROL_PERMISO (
    ROL      VARCHAR2(30 BYTE)  NOT NULL,
    PERMISO  VARCHAR2(60 BYTE)  NOT NULL,
    CONSTRAINT ROL_PERMISO_PK PRIMARY KEY (ROL, PERMISO),
    CONSTRAINT ROL_PERMISO_ROL_FK FOREIGN KEY (ROL) REFERENCES ICTC.ROL (CODIGO)
);

COMMENT ON TABLE ICTC.ROL_PERMISO IS 'Permisos asignados a los roles.';

-- -----------------------------------------------------------------------------
-- Asignaciones por cuenta. Una cuenta puede tener varios roles.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.USUARIO_ROL (
    USER_ID           NUMBER(10)         NOT NULL,
    ROL               VARCHAR2(30 BYTE)  NOT NULL,
    FECHA_ASIGNACION  DATE               DEFAULT SYSDATE NOT NULL,
    CONSTRAINT USUARIO_ROL_PK PRIMARY KEY (USER_ID, ROL),
    CONSTRAINT USUARIO_ROL_ROL_FK FOREIGN KEY (ROL) REFERENCES ICTC.ROL (CODIGO)
);

COMMENT ON TABLE ICTC.USUARIO_ROL IS 'Roles asignados por cuenta de usuario.';

-- -----------------------------------------------------------------------------
-- Historial de seguridad. ROL conserva el código incluso al eliminar el rol.
-- USUARIOS contiene los IDs separados por comas y DETALLE guarda el JSON.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.SEGURIDAD_EVENTO (
    ID             NUMBER(10)          NOT NULL,
    ACCION         VARCHAR2(30 BYTE)   NOT NULL,
    ROL            VARCHAR2(30 BYTE),
    USUARIOS       VARCHAR2(200 BYTE),
    DETALLE        VARCHAR2(4000 BYTE),
    AUTOR_ID       NUMBER(10)          NOT NULL,
    AUTOR_NOMBRE   VARCHAR2(255 CHAR)  NOT NULL,
    AUTOR_EMPRESA  VARCHAR2(20 BYTE),
    FECHA          DATE                NOT NULL,
    CONSTRAINT SEGURIDAD_EVENTO_PK PRIMARY KEY (ID),
    CONSTRAINT SEG_EVENTO_ACCION_CK
        CHECK (ACCION IN ('ROL_CREADO', 'ROL_EDITADO', 'ROL_ELIMINADO', 'PERMISOS_ROL', 'ROLES_USUARIO'))
);

COMMENT ON TABLE ICTC.SEGURIDAD_EVENTO IS 'Historial de cambios de roles, permisos y asignaciones.';

CREATE INDEX ICTC.SEG_EVENTO_FECHA_IX ON ICTC.SEGURIDAD_EVENTO (FECHA);

CREATE SEQUENCE ICTC.SEGURIDAD_EVENTO_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;

CREATE TRIGGER ICTC.SEGURIDAD_EVENTO_BI
BEFORE INSERT ON ICTC.SEGURIDAD_EVENTO
FOR EACH ROW
WHEN (NEW.ID IS NULL)
BEGIN
    SELECT ICTC.SEGURIDAD_EVENTO_SEQ.NEXTVAL INTO :NEW.ID FROM DUAL;
END;
/

DECLARE
    v_trigger_valido NUMBER;
BEGIN
    SELECT COUNT(*)
      INTO v_trigger_valido
      FROM user_objects
     WHERE object_name = 'SEGURIDAD_EVENTO_BI'
       AND object_type = 'TRIGGER'
       AND status = 'VALID';

    IF v_trigger_valido <> 1 THEN
        RAISE_APPLICATION_ERROR(-20003, 'Revisar USER_ERRORS para SEGURIDAD_EVENTO_BI.');
    END IF;
END;
/

-- -----------------------------------------------------------------------------
-- Matriz inicial de Administración y Comisiones.
-- -----------------------------------------------------------------------------
INSERT INTO ICTC.ROL (CODIGO, NOMBRE, ES_SISTEMA)
VALUES ('ADMINISTRADOR', 'Administrador', 'S');

INSERT INTO ICTC.ROL (CODIGO, NOMBRE, ES_SISTEMA)
VALUES ('GERENCIA', 'Gerencia', 'N');

INSERT ALL
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'administracion.laboratorios')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'administracion.usuarios')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'administracion.roles')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'comisiones.ver')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'comisiones.procesar')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'comisiones.enviar')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('ADMINISTRADOR', 'comisiones.ver_detalle')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('GERENCIA', 'comisiones.ver')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('GERENCIA', 'comisiones.aprobar')
    INTO ICTC.ROL_PERMISO (ROL, PERMISO) VALUES ('GERENCIA', 'comisiones.rechazar')
SELECT 1 FROM DUAL;

COMMIT;

-- -----------------------------------------------------------------------------
-- Alta inicial, reservada para revisión en el despliegue. Sustituir el correo
-- de ejemplo por el de la persona autorizada. Se incluyen todas sus cuentas
-- por DNI, o por correo para una persona sin DNI. Un correo ausente o ambiguo
-- detiene el bloque antes de insertar.
-- -----------------------------------------------------------------------------
-- DECLARE
--     v_correo ICTC.USERS.EMAIL%TYPE := 'administrador@ejemplo.test';
--     v_dni ICTC.USERS.IDENTIFICACION%TYPE;
--     v_personas NUMBER;
-- BEGIN
--     SELECT COUNT(DISTINCT CASE WHEN TRIM(IDENTIFICACION) IS NOT NULL
--                                THEN 'dni:' || TRIM(IDENTIFICACION)
--                                ELSE 'correo:' || LOWER(TRIM(EMAIL)) END),
--            MIN(TRIM(IDENTIFICACION))
--       INTO v_personas, v_dni
--       FROM ICTC.USERS
--      WHERE LOWER(TRIM(EMAIL)) = LOWER(TRIM(v_correo));
--
--     IF v_personas <> 1 THEN
--         RAISE_APPLICATION_ERROR(-20004, 'El correo debe identificar a una sola persona.');
--     END IF;
--
--     INSERT INTO ICTC.USUARIO_ROL (USER_ID, ROL)
--     SELECT u.ID, 'ADMINISTRADOR'
--       FROM ICTC.USERS u
--      WHERE ((v_dni IS NOT NULL AND TRIM(u.IDENTIFICACION) = v_dni)
--          OR (v_dni IS NULL AND TRIM(u.IDENTIFICACION) IS NULL
--              AND LOWER(TRIM(u.EMAIL)) = LOWER(TRIM(v_correo))))
--        AND NOT EXISTS (
--            SELECT 1 FROM ICTC.USUARIO_ROL r
--             WHERE r.USER_ID = u.ID AND r.ROL = 'ADMINISTRADOR'
--        );
--
--     COMMIT;
-- END;
-- /
