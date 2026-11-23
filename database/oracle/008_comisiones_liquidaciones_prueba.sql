-- =============================================================================
-- ICTC: liquidaciones de comisiones y roles del sistema, tablas DE PRUEBA.
-- Ejecutar como ICTC en modo script. Crea solo objetos con prefijo PRUEBA_;
-- no modifica USERS ni ninguna tabla existente.
-- Si hay un error, revisar los objetos creados antes de reintentar.
-- Vaciar los datos de prueba: 009_vaciar_comisiones_liquidaciones_prueba.sql.
-- =============================================================================

WHENEVER SQLERROR EXIT SQL.SQLCODE ROLLBACK

DECLARE
    v_existentes NUMBER;
BEGIN
    IF USER <> 'ICTC' OR SYS_CONTEXT('USERENV', 'CURRENT_SCHEMA') <> 'ICTC' THEN
        RAISE_APPLICATION_ERROR(-20001, 'Ejecutar desde una sesion del esquema ICTC.');
    END IF;

    SELECT COUNT(*)
      INTO v_existentes
      FROM user_objects
     WHERE object_name IN ('PRUEBA_USUARIO_ROL', 'PRUEBA_COM_LIQ', 'PRUEBA_COM_LIQ_DETALLE',
                           'PRUEBA_COM_LIQ_CONTRATO', 'PRUEBA_COM_LIQ_EVENTO', 'PRUEBA_COM_LIQ_ENVIO',
                           'PRUEBA_COM_LIQ_SEQ', 'PRUEBA_COM_LIQ_EVENTO_SEQ');

    IF v_existentes > 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Ya existen tablas PRUEBA_ de liquidaciones o roles.');
    END IF;
END;
/

-- -----------------------------------------------------------------------------
-- Roles del sistema: un rol por fila de USERS (cada empresa es una fila).
-- Para agregar un rol nuevo hay que ampliar PRUEBA_USUARIO_ROL_CK.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_USUARIO_ROL (
    USER_ID           NUMBER(10)         NOT NULL,
    ROL               VARCHAR2(30 BYTE)  NOT NULL,
    FECHA_ASIGNACION  DATE               DEFAULT SYSDATE NOT NULL,
    CONSTRAINT PRUEBA_USUARIO_ROL_PK PRIMARY KEY (USER_ID, ROL),
    CONSTRAINT PRUEBA_USUARIO_ROL_CK CHECK (ROL IN ('ADMINISTRADOR', 'GERENCIA'))
);

-- -----------------------------------------------------------------------------
-- Liquidación: una fila por cada vez que se procesa un período. Al rechazarla
-- queda como RECHAZADA (historial) y el siguiente proceso crea otra fila. Solo
-- puede haber una no rechazada por período (PRUEBA_COM_LIQ_ACTIVA_UX).
-- DESGLOSE guarda en JSON lo que muestra la pantalla al procesar.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_COM_LIQ (
    ID              NUMBER(10)          NOT NULL,
    PERIODO         VARCHAR2(7 BYTE)    NOT NULL,
    TIPO            CHAR(1 BYTE)        NOT NULL,
    ESTADO          VARCHAR2(20 BYTE)   NOT NULL,
    FECHA_DESDE     DATE                NOT NULL,
    FECHA_HASTA     DATE                NOT NULL,
    TOTAL           NUMBER(14,2)        NOT NULL,
    CARGA_ID        NUMBER(10),
    DESGLOSE        CLOB                NOT NULL,
    FECHA_CREACION  DATE                DEFAULT SYSDATE NOT NULL,
    CONSTRAINT PRUEBA_COM_LIQ_PK PRIMARY KEY (ID),
    CONSTRAINT PRUEBA_COM_LIQ_TIPO_CK CHECK (TIPO IN ('M', 'T')),
    CONSTRAINT PRUEBA_COM_LIQ_ESTADO_CK CHECK (ESTADO IN ('PROCESADA', 'APROBADA', 'RECHAZADA', 'ENVIADA'))
);

CREATE UNIQUE INDEX ICTC.PRUEBA_COM_LIQ_ACTIVA_UX
    ON ICTC.PRUEBA_COM_LIQ (CASE WHEN ESTADO <> 'RECHAZADA' THEN PERIODO END);

CREATE SEQUENCE ICTC.PRUEBA_COM_LIQ_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;

CREATE TRIGGER ICTC.PRUEBA_COM_LIQ_BI
BEFORE INSERT ON ICTC.PRUEBA_COM_LIQ
FOR EACH ROW
WHEN (NEW.ID IS NULL)
BEGIN
    SELECT ICTC.PRUEBA_COM_LIQ_SEQ.NEXTVAL INTO :NEW.ID FROM DUAL;
END;
/

-- -----------------------------------------------------------------------------
-- Detalle: una fila por colaborador y meta, con la regla que se aplicó.
-- META_ID = 0 es el bono pronta venta. ADELANTO y PENDIENTE solo en las
-- liquidaciones trimestrales. A_PAGAR es lo que se paga en esta liquidación.
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_COM_LIQ_DETALLE (
    LIQ_ID           NUMBER(10)          NOT NULL,
    USER_ID          NUMBER(10)          NOT NULL,
    META_ID          NUMBER(10)          NOT NULL,
    NOMBRE           VARCHAR2(150 BYTE)  NOT NULL,
    CODIGO_NETSUITE  VARCHAR2(150 BYTE)  NOT NULL,
    ROL_NETSUITE     VARCHAR2(100 BYTE)  NOT NULL,
    LINEA            VARCHAR2(30 BYTE)   NOT NULL,
    ATRIBUCION       VARCHAR2(20 BYTE)   NOT NULL,
    TIPO_META        VARCHAR2(20 BYTE)   NOT NULL,
    BASE_TRAMO       VARCHAR2(20 BYTE)   NOT NULL,
    MINIMO           NUMBER(14,2)        NOT NULL,
    CANTIDAD         NUMBER(10)          NOT NULL,
    MONTO            NUMBER(14,2)        NOT NULL,
    TRAMO_DESDE      NUMBER(14,2),
    TRAMO_HASTA      NUMBER(14,2),
    TRAMO_VALOR      NUMBER(14,4),
    COMISION         NUMBER(14,2)        NOT NULL,
    ADELANTO         NUMBER(14,2),
    PENDIENTE        NUMBER(14,2),
    A_PAGAR          NUMBER(14,2)        NOT NULL,
    CONSTRAINT PRUEBA_COM_LIQ_DETALLE_PK PRIMARY KEY (LIQ_ID, USER_ID, META_ID),
    CONSTRAINT PRUEBA_COM_LIQ_DETALLE_FK FOREIGN KEY (LIQ_ID) REFERENCES ICTC.PRUEBA_COM_LIQ (ID)
);

-- -----------------------------------------------------------------------------
-- Contratos que contaron para cada colaborador y línea. Respaldo de lo
-- aprobado y base del trimestre (ventas de los tres meses aprobados).
-- SERVICIO solo en morosos (Sangre y Tejido del mismo contrato son dos).
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_COM_LIQ_CONTRATO (
    LIQ_ID           NUMBER(10)          NOT NULL,
    USER_ID          NUMBER(10)          NOT NULL,
    LINEA            VARCHAR2(30 BYTE)   NOT NULL,
    NUMERO_CONTRATO  VARCHAR2(50 BYTE)   NOT NULL,
    SERVICIO         VARCHAR2(200 BYTE),
    FECHA_EMISION    DATE                NOT NULL,
    MONTO            NUMBER(14,2)        NOT NULL,
    FILAS            NUMBER(5)           NOT NULL,
    CONSTRAINT PRUEBA_COM_LIQ_CONTRATO_FK FOREIGN KEY (LIQ_ID) REFERENCES ICTC.PRUEBA_COM_LIQ (ID)
);

CREATE INDEX ICTC.PRUEBA_COM_LIQ_CONTRATO_IX
    ON ICTC.PRUEBA_COM_LIQ_CONTRATO (LIQ_ID, USER_ID, LINEA);

-- -----------------------------------------------------------------------------
-- Historial: quién hizo cada paso, cuándo, con qué empresa entró y el motivo
-- (obligatorio en un rechazo).
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_COM_LIQ_EVENTO (
    ID          NUMBER(10)           NOT NULL,
    LIQ_ID      NUMBER(10)           NOT NULL,
    ACCION      VARCHAR2(20 BYTE)    NOT NULL,
    USER_ID     NUMBER(10)           NOT NULL,
    NOMBRE      VARCHAR2(150 BYTE)   NOT NULL,
    EMPRESA     VARCHAR2(3 BYTE)     NOT NULL,
    COMENTARIO  VARCHAR2(1000 BYTE),
    FECHA       DATE                 DEFAULT SYSDATE NOT NULL,
    CONSTRAINT PRUEBA_COM_LIQ_EVENTO_PK PRIMARY KEY (ID),
    CONSTRAINT PRUEBA_COM_LIQ_EVENTO_FK FOREIGN KEY (LIQ_ID) REFERENCES ICTC.PRUEBA_COM_LIQ (ID),
    CONSTRAINT PRUEBA_COM_LIQ_EVENTO_CK CHECK (ACCION IN ('PROCESO', 'APROBACION', 'RECHAZO', 'ENVIO', 'REENVIO'))
);

CREATE INDEX ICTC.PRUEBA_COM_LIQ_EVENTO_IX ON ICTC.PRUEBA_COM_LIQ_EVENTO (LIQ_ID, ID);

CREATE SEQUENCE ICTC.PRUEBA_COM_LIQ_EVENTO_SEQ START WITH 1 INCREMENT BY 1 NOCACHE;

CREATE TRIGGER ICTC.PRUEBA_COM_LIQ_EVENTO_BI
BEFORE INSERT ON ICTC.PRUEBA_COM_LIQ_EVENTO
FOR EACH ROW
WHEN (NEW.ID IS NULL)
BEGIN
    SELECT ICTC.PRUEBA_COM_LIQ_EVENTO_SEQ.NEXTVAL INTO :NEW.ID FROM DUAL;
END;
/

-- -----------------------------------------------------------------------------
-- Envío al personal: un correo por colaborador. ESTADO: P pendiente,
-- E enviado, F fallido (se puede reenviar).
-- -----------------------------------------------------------------------------
CREATE TABLE ICTC.PRUEBA_COM_LIQ_ENVIO (
    LIQ_ID       NUMBER(10)           NOT NULL,
    USER_ID      NUMBER(10)           NOT NULL,
    CORREO       VARCHAR2(150 BYTE)   NOT NULL,
    ESTADO       CHAR(1 BYTE)         NOT NULL,
    INTENTOS     NUMBER(3)            DEFAULT 0 NOT NULL,
    FECHA_ENVIO  DATE,
    ERROR        VARCHAR2(1000 BYTE),
    CONSTRAINT PRUEBA_COM_LIQ_ENVIO_PK PRIMARY KEY (LIQ_ID, USER_ID),
    CONSTRAINT PRUEBA_COM_LIQ_ENVIO_FK FOREIGN KEY (LIQ_ID) REFERENCES ICTC.PRUEBA_COM_LIQ (ID),
    CONSTRAINT PRUEBA_COM_LIQ_ENVIO_CK CHECK (ESTADO IN ('P', 'E', 'F'))
);

DECLARE
    v_triggers_invalidos NUMBER;
BEGIN
    SELECT COUNT(*)
      INTO v_triggers_invalidos
      FROM user_objects
     WHERE object_name IN ('PRUEBA_COM_LIQ_BI', 'PRUEBA_COM_LIQ_EVENTO_BI')
       AND object_type = 'TRIGGER'
       AND status <> 'VALID';

    IF v_triggers_invalidos > 0 THEN
        RAISE_APPLICATION_ERROR(-20003, 'Revisar USER_ERRORS para los triggers PRUEBA_COM_LIQ.');
    END IF;
END;
/

-- -----------------------------------------------------------------------------
-- Roles para probar. Cada correo tiene una fila por empresa (001 y 002): se
-- asigna a las dos para que el reporte funcione entrando con cualquiera.
-- -----------------------------------------------------------------------------
INSERT INTO ICTC.PRUEBA_USUARIO_ROL (USER_ID, ROL)
SELECT ID, 'ADMINISTRADOR' FROM ICTC.USERS WHERE LOWER(EMAIL) = 'soporteti.peru@cryoholdco.com';

INSERT INTO ICTC.PRUEBA_USUARIO_ROL (USER_ID, ROL)
SELECT ID, 'GERENCIA' FROM ICTC.USERS WHERE LOWER(EMAIL) = 'mtest@crio.com';

COMMIT;

-- Verificación: deben salir 4 filas (IDs 2 y 3 ADMINISTRADOR, 155 y 156 GERENCIA).
SELECT r.USER_ID, u.EMAIL, u.EMPRESA, r.ROL
  FROM ICTC.PRUEBA_USUARIO_ROL r
  JOIN ICTC.USERS u ON u.ID = r.USER_ID
 ORDER BY r.ROL, u.EMPRESA;
