import type { ContratoInforme, ContratoSeleccionado } from "./tipos";

/** Máximo de contratos que se pueden marcar para un envío masivo. */
export const MAXIMO_SELECCION = 50;

export function tieneCorreo(contrato: ContratoInforme): boolean {
    return (contrato.mail_contacto ?? "").trim() !== "";
}

/**
 * Un contrato se puede marcar para el envío si tiene correo de contacto y laboratorio
 * no le falta la carpeta (si la unidad no responde, se deja intentar).
 */
export function puedeSeleccionarse(contrato: ContratoInforme): boolean {
    return tieneCorreo(contrato) && contrato.tiene_carpeta !== false;
}

export function motivoNoSeleccionable(contrato: ContratoInforme): string | null {
    if (contrato.tiene_carpeta === false) {
        return "No hay carpeta de documentos para este contrato.";
    }

    if (!tieneCorreo(contrato)) {
        return "El cliente no tiene correo de contacto.";
    }

    return null;
}

export function aSeleccionado(contrato: ContratoInforme): ContratoSeleccionado {
    return {
        numero_base: contrato.numero_base,
        familia: contrato.familia,
        nombre: contrato.nombre,
        identificacion: contrato.identificacion,
        enviado: contrato.enviado,
    };
}
