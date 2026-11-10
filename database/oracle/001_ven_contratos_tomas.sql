-- =============================================================================
--  ICTC.VEN_CONTRATOS_TOMAS
--  Una fila por toma de muestra serológica. La toma es del CONTRATO BASE,
--  no del contrato de cada servicio.
--
--  Por qué base y no NUMERO_CONTRATO:
--  el número lleva el servicio pegado al final. Estos tres
--
--      PE-CR-2026-022092-2-S
--      PE-CR-2026-022092-2-T
--      PE-CR-2026-022092-2-P
--
--  son el mismo contrato, y a la señora se le saca sangre UNA vez. Guardar la
--  toma por NUMERO_CONTRATO la repetiría tres veces. La base es todo lo que
--  está antes del último guion:
--
--      SUBSTR(n, 1, INSTR(n, '-', -1) - 1)   -->  PE-CR-2026-022092-2
--
--  Esa regla es segura: en los 1.277 contratos del formato PE- el último
--  segmento es siempre una letra de servicio, y en el formato anterior
--  (10000-S) también.
--
--  Solo llevan serología los servicios S y T. De las 545 bases del formato
--  nuevo, 453 tienen S o T y 92 no tienen ninguno: esas no entran.
--
--  Por qué una tabla nueva y no ampliar VEN_CONTRATOS_LABORATORIOS:
--  esa tabla tiene la PK en (NO_CIA, NUMERO_CONTRATO), o sea una fila por
--  contrato POR SERVICIO. No puede guardar ni la segunda toma ni la idea de
--  base. Aquí la PK es (NO_CIA, NUMERO_CONTRATO_BASE, NUMERO_TOMA).
--
--  El estado sale de las dos fechas, sin columna de estado:
--    FECHA_TOMA IS NULL ......................... falta tomarse la prueba
--    FECHA_TOMA NOT NULL, FECHA_RESULTADO NULL .. muestra tomada
--    FECHA_RESULTADO NOT NULL ................... examen recibido
--
--  No se carga el historial a propósito. Aquí entran las bases del mes en
--  curso (003_) y las que se creen de ahí en adelante, que las pone el
--  trigger (002_). Lo viejo se queda en VEN_CONTRATOS_LABORATORIOS.
--
--  Oracle 10g: sin IF NOT EXISTS, sin DEFAULT ON NULL. Si hay que repetir
--  el script, primero DROP TABLE ICTC.VEN_CONTRATOS_TOMAS CASCADE CONSTRAINTS.
-- =============================================================================

CREATE TABLE ICTC.VEN_CONTRATOS_TOMAS (
    NO_CIA                VARCHAR2(3)   NOT NULL,
    NUMERO_CONTRATO_BASE  VARCHAR2(30)  NOT NULL,
    NUMERO_TOMA           NUMBER(2)     NOT NULL,
    FECHA_TOMA            DATE,
    FECHA_RESULTADO       DATE,
    LABORATORIO_TOMA      VARCHAR2(15),
    SEDE_TOMA             VARCHAR2(2)
);

-- Los tipos salen de las tablas padre, para que los JOIN no conviertan:
--   NO_CIA, NUMERO_CONTRATO_BASE = VEN_CONTRATOS (misma longitud, sin sufijo)
--   LABORATORIO_TOMA             = LAB_LABORATORIOS_SEDES.IDENTIFICACION (RUC)
--   SEDE_TOMA                    = LAB_LABORATORIOS_SEDES.CODIGO_SEDE

ALTER TABLE ICTC.VEN_CONTRATOS_TOMAS
    ADD CONSTRAINT PK_VEN_CONTRATOS_TOMAS
    PRIMARY KEY (NO_CIA, NUMERO_CONTRATO_BASE, NUMERO_TOMA);

-- OJO: no hay FK contra VEN_CONTRATOS, y no es un olvido.
-- Una FK necesita una clave única en la tabla padre, y la base no es clave
-- de nada: VEN_CONTRATOS está por (NO_CIA, NUMERO_CONTRATO), con el servicio
-- incluido. No existe ninguna tabla de contratos base.
--
-- Lo que se pierde con eso:
--   - se puede insertar una base que no existe;
--   - al borrar un contrato su toma no se va sola (antes lo hacía el CASCADE).
-- Las dos cosas las vigila la consulta 5 de 004_verificacion.sql.

-- Laboratorio y sede van juntos: o los tres datos, o ninguno. Oracle no
-- valida la FK cuando alguna columna es NULL, que es justo lo que queremos
-- mientras la toma no se ha hecho.
ALTER TABLE ICTC.VEN_CONTRATOS_TOMAS
    ADD CONSTRAINT FK_VCT_SEDE
    FOREIGN KEY (NO_CIA, LABORATORIO_TOMA, SEDE_TOMA)
    REFERENCES ICTC.LAB_LABORATORIOS_SEDES (NO_CIA, IDENTIFICACION, CODIGO_SEDE);

-- Primera o segunda toma. Si algún día hace falta una tercera, es esta línea.
ALTER TABLE ICTC.VEN_CONTRATOS_TOMAS
    ADD CONSTRAINT CK_VCT_NUMERO_TOMA
    CHECK (NUMERO_TOMA IN (1, 2));

-- No puede llegar un resultado de una muestra que nunca se tomó.
ALTER TABLE ICTC.VEN_CONTRATOS_TOMAS
    ADD CONSTRAINT CK_VCT_ORDEN_FECHAS
    CHECK (FECHA_RESULTADO IS NULL OR FECHA_TOMA IS NOT NULL);

-- La base no puede traer el servicio pegado: si termina en guion y una sola
-- letra, alguien guardó el número completo en vez de la base. Rechaza
-- "...-022092-2-S" y deja pasar "...-022092-2", que acaba en dígito.
ALTER TABLE ICTC.VEN_CONTRATOS_TOMAS
    ADD CONSTRAINT CK_VCT_BASE_SIN_SERVICIO
    CHECK (NOT REGEXP_LIKE(NUMERO_CONTRATO_BASE, '-[A-Za-z]$'));

-- Para "¿qué tomas hizo esta sede?" y para que un borrado en
-- LAB_LABORATORIOS_SEDES no bloquee la tabla entera.
CREATE INDEX ICTC.IX_VCT_SEDE
    ON ICTC.VEN_CONTRATOS_TOMAS (NO_CIA, LABORATORIO_TOMA, SEDE_TOMA);

COMMENT ON TABLE ICTC.VEN_CONTRATOS_TOMAS IS
    'Tomas de muestra serologica. Una fila por contrato BASE y numero de toma, no por servicio.';
COMMENT ON COLUMN ICTC.VEN_CONTRATOS_TOMAS.NUMERO_CONTRATO_BASE IS
    'Numero de contrato SIN el sufijo de servicio. No hace join directo con VEN_CONTRATOS.NUMERO_CONTRATO.';
COMMENT ON COLUMN ICTC.VEN_CONTRATOS_TOMAS.NUMERO_TOMA IS
    '1 = primera toma, 2 = segunda toma (recitacion).';
COMMENT ON COLUMN ICTC.VEN_CONTRATOS_TOMAS.FECHA_TOMA IS
    'Fecha en que se tomo la muestra. NULL = aun no se la toma.';
COMMENT ON COLUMN ICTC.VEN_CONTRATOS_TOMAS.FECHA_RESULTADO IS
    'Fecha en que Criocord recibio el resultado. NULL = aun no llega.';
COMMENT ON COLUMN ICTC.VEN_CONTRATOS_TOMAS.LABORATORIO_TOMA IS
    'RUC del laboratorio: LAB_LABORATORIOS_SEDES.IDENTIFICACION.';
COMMENT ON COLUMN ICTC.VEN_CONTRATOS_TOMAS.SEDE_TOMA IS
    'Sede del laboratorio: LAB_LABORATORIOS_SEDES.CODIGO_SEDE.';
