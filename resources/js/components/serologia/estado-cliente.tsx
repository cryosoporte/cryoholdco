import { Badge } from "@/components/ui/badge";
import type { Resultado } from "./tipos";
import { estados, avisosPlazo } from "./configuracion";

export function EstadoCliente({ fila }: { fila: Resultado }) {
    const estado =
        fila.serologia === "S"
            ? estados.recibido
            : fila.fecha_toma !== null
              ? estados.tomada
              : estados.pendiente;

    const avisoPlazo = fila.plazo ? avisosPlazo[fila.plazo.situacion] : null;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Badge className={`${estado.clase} py-3 px-3`}>
                {estado.texto}
            </Badge>

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
