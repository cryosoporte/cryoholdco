-- =============================================================================
--  Trigger: cada contrato base nuevo de serología nace con su toma pendiente.
--
--  Tiene que vivir en la base y no en Laravel porque quien inserta en
--  VEN_CONTRATOS es el sistema anterior, no esta aplicación.
--
--  Qué hace, en orden:
--    1. Separa el servicio (lo que va después del último guion).
--    2. Si el servicio no es S ni T, no hace nada: esos no llevan serología.
--    3. Calcula la base y le inserta la toma 1.
--
--  Un contrato con S y T entra dos veces, una por cada servicio. La segunda
--  choca con la PK y DUP_VAL_ON_INDEX la ignora: queda UNA sola toma por
--  base, que es justo lo que se quiere. Lo mismo si se vuelve a correr la
--  carga del mes.
--
--  OJO: este es el único objeto de todo el cambio que toca una tabla del
--  sistema anterior. Si el INSERT de la toma falla, el INSERT del contrato
--  falla con él y el sistema de ventas se cae. Por eso:
--
--    - La fila que inserta solo tiene las tres columnas obligatorias, todas
--      con valor garantizado; las demás quedan NULL.
--    - No hay FK contra VEN_CONTRATOS, así que no hay orden de inserción que
--      pueda fallar.
--    - Un número sin guion (el formato viejo, de 1900 a 2019) sale por el
--      RETURN y no intenta nada.
--
--  Si prefieres que el trigger JAMÁS pueda frenar la creación de un contrato,
--  cambia el bloque EXCEPTION por "WHEN OTHERS THEN NULL;". A cambio, una
--  falla pasa callada: la consulta 3 de 004_verificacion.sql es la que te
--  avisaría.
-- =============================================================================

CREATE OR REPLACE TRIGGER ICTC.TR_VEN_CONTRATOS_TOMA_INI
AFTER INSERT ON ICTC.VEN_CONTRATOS
FOR EACH ROW
DECLARE
    v_guion    PLS_INTEGER;
    v_servicio VARCHAR2(30);
    v_base     VARCHAR2(30);
BEGIN
    v_guion := INSTR(:NEW.NUMERO_CONTRATO, '-', -1);

    -- Sin guion no hay servicio que mirar: formato antiguo, se ignora.
    IF v_guion = 0 THEN
        RETURN;
    END IF;

    v_servicio := SUBSTR(:NEW.NUMERO_CONTRATO, v_guion + 1);

    -- Solo S y T llevan serología.
    IF v_servicio NOT IN ('S', 'T') THEN
        RETURN;
    END IF;

    v_base := SUBSTR(:NEW.NUMERO_CONTRATO, 1, v_guion - 1);

    INSERT INTO ICTC.VEN_CONTRATOS_TOMAS (
        NO_CIA,
        NUMERO_CONTRATO_BASE,
        NUMERO_TOMA
    ) VALUES (
        :NEW.NO_CIA,
        v_base,
        1
    );
EXCEPTION
    -- La base ya tiene su toma: es el servicio hermano (S o T). Correcto.
    WHEN DUP_VAL_ON_INDEX THEN
        NULL;
END;
/

-- Para desactivarlo sin borrarlo:
-- ALTER TRIGGER ICTC.TR_VEN_CONTRATOS_TOMA_INI DISABLE;
