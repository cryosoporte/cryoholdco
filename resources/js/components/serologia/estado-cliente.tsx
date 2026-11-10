import { Badge } from "@/components/ui/badge";
import type { Plazo, Resultado } from "./tipos";
import { avisosPlazo } from "./configuracion";
import { EstadoPruebaBadge, type EstadoPrueba } from "./estado-prueba";

export function obtenerEstadoCliente(fila: Resultado): EstadoPrueba {
    if (fila.serologia === "S") {
        return "recibido";
    }

    return fila.fecha_toma === null ? "pendiente" : "tomada";
}

export function EstadoCliente({
    fila,
    situacionPlazo = fila.plazo?.situacion,
}: {
    fila: Resultado;
    situacionPlazo?: Plazo["situacion"] | null;
}) {
    const avisoPlazo = situacionPlazo ? avisosPlazo[situacionPlazo] : null;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <EstadoPruebaBadge estado={obtenerEstadoCliente(fila)} />

            {avisoPlazo && (
                <Badge
                    className={`${avisoPlazo.clase} rounded-md px-2 py-0.5 text-xs`}
                >
                    {avisoPlazo.texto}
                </Badge>
            )}
        </div>
    );
}
