export function formatearFecha(fecha: string | null): string {
    if (!fecha) {
        return "—";
    }

    const partes = fecha.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!partes) {
        return fecha;
    }

    const [, anio, mes, dia] = partes;

    return `${dia}-${mes}-${anio}`;
}

/**
 * Fecha y hora tal como las envía Laravel en ISO 8601 (2026-11-17T10:32:00-05:00),
 * sin convertir de zona: muestra la hora con la que se guardó.
 */
export function formatearFechaHora(fecha: string | null): string {
    if (!fecha) {
        return "—";
    }

    const partes = fecha.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);

    if (!partes) {
        return formatearFecha(fecha);
    }

    const [, anio, mes, dia, hora, minutos] = partes;

    return `${dia}-${mes}-${anio} ${hora}:${minutos}`;
}
