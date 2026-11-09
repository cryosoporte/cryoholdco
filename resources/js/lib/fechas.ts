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
