import { CheckIcon, CornerDownLeftIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { pasos } from "./configuracion";
import type { EstadoLiquidacion, Liquidacion } from "./tipos";

const pasoActual: Record<EstadoLiquidacion, number> = {
    borrador: 0,
    enviada: 1,
    rechazada: 1,
    aprobada: 2,
    enviada_personal: 3,
};

export function PasosLiquidacion({ liquidacion }: { liquidacion: Liquidacion }) {
    const actual = pasoActual[liquidacion.estado];
    const aprobacion = liquidacion.historial.find((evento) => evento.tipo === "aprobada");
    const rechazado = liquidacion.estado === "rechazada";
    const enviados = liquidacion.envios.filter((envio) => envio.estado === "enviado").length;
    const detalles = [
        `${liquidacion.base.filas.toLocaleString("es-PE")} filas de ${liquidacion.base.fuente}`,
        rechazado ? "Devuelta por gerencia" : actual >= 1 ? "Correo enviado a gerencia" : "Pendiente de procesar",
        aprobacion ? `Por ${aprobacion.por}` : "Pendiente de aprobación",
        actual >= 2 ? `${enviados} de ${liquidacion.envios.length} correos` : "Después de la aprobación",
    ];

    return (
        <ol aria-label="Progreso de la liquidación" className="grid grid-cols-1 gap-px overflow-hidden rounded-xl border bg-border md:grid-cols-4">
            {Object.values(pasos).map((paso, indice) => {
                const devuelto = rechazado && indice === 1;
                const completo = indice <= actual && !devuelto;

                return (
                    <li
                        key={paso}
                        aria-current={indice === actual ? "step" : undefined}
                        className={cn(
                            "flex min-w-0 items-start gap-3 bg-card px-4 py-3",
                            indice === actual && "border-l-2 border-l-primary md:border-l-0 md:border-t-2 md:border-t-primary",
                            devuelto && "border-l-amber-500 md:border-t-amber-500",
                        )}
                    >
                        <span aria-hidden="true" className={cn(
                            "flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-medium",
                            completo && "border-primary/20 bg-primary/10 text-primary",
                            devuelto && "border-amber-600/30 bg-amber-500/10 text-amber-800 dark:text-amber-300",
                            !completo && !devuelto && "text-muted-foreground",
                        )}>
                            {devuelto ? <CornerDownLeftIcon className="size-3.5" /> : completo ? <CheckIcon className="size-3.5" /> : indice + 1}
                        </span>
                        <div>
                            <p className="text-sm font-semibold">{paso}</p>
                            <p className="text-xs leading-5 text-muted-foreground">{detalles[indice]}</p>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
