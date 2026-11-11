import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from "@/components/ui/dialog";
import { EstadoCliente, obtenerEstadoCliente } from "./estado-cliente";
import type { Resultado } from "./tipos";

type DetalleClienteProps = {
    cliente: Resultado | null;
    onCerrar: () => void;
};

const formatoFecha = new Intl.DateTimeFormat("es-PE", {
    day: "numeric",
    month: "short",
    timeZone: "UTC",
});

function formatearFechaCorta(fecha: string): string {
    const dia = new Date(`${fecha.slice(0, 10)}T00:00:00Z`);

    return Number.isNaN(dia.getTime()) ? "—" : formatoFecha.format(dia);
}

function DatosCliente({ cliente }: { cliente: Resultado }) {
    const estado = obtenerEstadoCliente(cliente);
    const datos = cliente.detalle;
    const apertura = datos?.plazo.fecha_apertura;
    const limite = datos?.plazo.fecha_limite;
    const plazo =
        apertura && limite
            ? `${formatearFechaCorta(apertura)} – ${formatearFechaCorta(limite)}`
            : "—";
    const toma = cliente.fecha_toma
        ? [formatearFechaCorta(cliente.fecha_toma), datos?.sede_toma]
              .filter(Boolean)
              .join(", ")
        : estado === "pendiente" ? "Pendiente" : "—";
    const resultado = estado === "recibido"
        ? cliente.fecha_resultado
            ? `Recibido ${formatearFechaCorta(cliente.fecha_resultado)}`
            : "Recibido · Sin fecha"
        : "Pendiente";

    const campos = [
        ["DNI", cliente.identificacion],
        ["Celular", datos?.celular],
        ["Asesor", cliente.asesor_nombre ?? cliente.asesor_dni],
        ["Código de contrato", cliente.numero_base],
        ["FPP", datos?.fpp ? formatearFechaCorta(datos.fpp) : null],
        ["Parto real", datos?.parto_real ? formatearFechaCorta(datos.parto_real) : null],
        ["Plazo", plazo],
        ["Toma", toma],
        ["Resultado", resultado],
    ];

    return (
        <dl className="grid grid-cols-2 gap-x-5 gap-y-4 sm:grid-cols-3">
            {campos.map(([etiqueta, valor]) => (
                <div key={etiqueta}>
                    <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {etiqueta}
                    </dt>
                    <dd className="mt-1 break-words font-semibold">
                        {valor ?? "—"}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function DetalleCliente({ cliente, onCerrar }: DetalleClienteProps) {
    return (
        <Dialog
            open={cliente !== null}
            onOpenChange={(abierto) => {
                if (!abierto) {
                    onCerrar();
                }
            }}
        >
            <DialogContent className="max-w-3xl gap-6">
                {cliente && (
                    <>
                        <DialogHeader className="border-b pb-4">
                            <DialogTitle className="text-2xl font-bold">
                                {cliente.nombre}
                            </DialogTitle>
                            <DialogDescription className="sr-only">
                                Detalle del contrato y seguimiento de la prueba
                                serológica.
                            </DialogDescription>

                            <EstadoCliente
                                fila={cliente}
                                situacionPlazo={
                                    obtenerEstadoCliente(cliente) === "pendiente"
                                        ? cliente.detalle?.plazo.situacion ?? null
                                        : null
                                }
                            />
                        </DialogHeader>

                        <DatosCliente cliente={cliente} />
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
