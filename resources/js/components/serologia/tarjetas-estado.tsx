import { opcionesEstado, estados } from "./configuracion";
import type { EstadoSerologia } from "./tipos";
import { Button } from "@/components/ui/button";

type TarjetasEstadoProps = {
    conteos: Record<EstadoSerologia, number>;
    estadoSeleccionado: EstadoSerologia | null;
    onSeleccionarEstado: (estado: EstadoSerologia) => void;
};

export function TarjetasEstado({
    conteos,
    estadoSeleccionado,
    onSeleccionarEstado,
}: TarjetasEstadoProps) {
    return (
        <div
            className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
            role="group"
            aria-label="Filtrar por estado"
        >
            {opcionesEstado.map((opcion) => {
                const configuracion = estados[opcion];
                const seleccionado = estadoSeleccionado === opcion;

                return (
                    <Button
                        variant="outline"
                        key={opcion}
                        type="button"
                        aria-pressed={seleccionado}
                        aria-label={`${configuracion.texto}: ${conteos[opcion]} registros`}
                        onClick={() => onSeleccionarEstado(opcion)}
                        className={`h-auto w-full cursor-pointer flex-col items-start gap-1 whitespace-normal border-l-4 bg-card p-4 text-left text-card-foreground ${configuracion.borde} ${seleccionado ? `ring-2 ${configuracion.seleccion}` : ""}`}
                    >
                        <span className="text-3xl font-bold tabular-nums">
                            {conteos[opcion]}
                        </span>
                        <span className="text-sm font-semibold">
                            {configuracion.texto}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {configuracion.descripcion}
                        </span>
                    </Button>
                );
            })}
        </div>
    );
}
