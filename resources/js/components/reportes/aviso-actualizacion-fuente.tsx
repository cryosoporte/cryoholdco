import { formatearFechaHora } from "@/lib/fechas";
import type { ActualizacionFuente } from "./tipos";

export function AvisoActualizacionFuente({
    fuente,
}: {
    fuente: ActualizacionFuente | null;
}) {
    if (fuente === null) {
        return (
            <p className="text-sm text-muted-foreground">
                La fuente de datos de este reporte aún no está registrada.
            </p>
        );
    }

    return (
        <p className="text-sm text-muted-foreground">
            {fuente.fecha_actualizacion ? (
                <>
                    Datos de <strong>{fuente.nombre}</strong> actualizados el{" "}
                    {formatearFechaHora(fuente.fecha_actualizacion, " a las ")}
                    {fuente.actualizado_por && ` por ${fuente.actualizado_por}`}.
                </>
            ) : (
                <>
                    La fuente <strong>{fuente.nombre}</strong> aún no tiene datos
                    cargados.
                </>
            )}
        </p>
    );
}
