import { opcionesEstado, estados } from "./configuracion";
import type { EstadoSerologia } from "./tipos";

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
                    <button
                        key={opcion}
                        type="button"
                        aria-pressed={seleccionado}
                        aria-label={`${configuracion.texto}: ${conteos[opcion]} registros`}
                        onClick={() => onSeleccionarEstado(opcion)}
                        className={`flex cursor-pointer flex-col gap-1 rounded-xl border border-l-4 bg-card p-4 text-left text-card-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${configuracion.borde} ${seleccionado ? `ring-2 ${configuracion.seleccion}` : ""}`}
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
                    </button>
                );
            })}
        </div>
    );
}
