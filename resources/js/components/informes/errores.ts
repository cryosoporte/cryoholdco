import { HttpResponseError } from "@inertiajs/core";

/**
 * El mensaje que el servidor devolvió para un error HTTP (por ejemplo "Este contrato no
 * tiene carpeta de documentos."), o el indicado si la respuesta no trae uno legible.
 */
export function mensajeDeError(
    error: unknown,
    mensajePorDefecto: string,
): string {
    if (!(error instanceof HttpResponseError)) {
        return mensajePorDefecto;
    }

    if ([401, 419].includes(error.response.status)) {
        return "Tu sesión caducó. Recarga la página e intenta de nuevo.";
    }

    try {
        const { message } = JSON.parse(error.response.data) as {
            message?: unknown;
        };

        return typeof message === "string" && message !== ""
            ? message
            : mensajePorDefecto;
    } catch {
        return mensajePorDefecto;
    }
}
