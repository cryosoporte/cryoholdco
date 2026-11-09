import { Dialog } from "@base-ui/react/dialog";
import { formatearFecha } from "@/lib/fechas";
import { EstadoCliente } from "./estado-cliente";
import { PlazoCliente } from "./plazo-cliente";
import type { Resultado } from "./tipos";

type DetalleClienteProps = {
    cliente: Resultado | null;
    onCerrar: () => void;
};

export function DetalleCliente({ cliente, onCerrar }: DetalleClienteProps) {
    return (
        <Dialog.Root
            open={cliente !== null}
            onOpenChange={(abierto) => {
                if (!abierto) {
                    onCerrar();
                }
            }}
        >
            <Dialog.Portal>
                <Dialog.Backdrop className="fixed inset-0 z-50 bg-black/40" />

                <Dialog.Popup className="fixed left-1/2 top-1/2 z-50 max-h-[90dvh] w-[calc(100%-2rem)] max-w-3xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-2xl bg-background text-foreground shadow-xl">
                    {cliente && (
                        <>
                            <div className="flex items-start justify-between gap-4 border-b p-6">
                                <div>
                                    <Dialog.Title className="text-2xl font-bold">
                                        {cliente.nombre}
                                    </Dialog.Title>

                                    <div className="mt-3">
                                        <EstadoCliente fila={cliente} />
                                    </div>
                                </div>

                                <Dialog.Close
                                    aria-label="Cerrar detalle del cliente"
                                    className="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-muted text-xl hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    ×
                                </Dialog.Close>
                            </div>

                            <div className="space-y-6 p-6">
                                <dl className="grid grid-cols-1 gap-6 sm:grid-cols-3">
                                    {[
                                        ["DNI", cliente.identificacion],
                                        ["Número base", cliente.numero_base],
                                        [
                                            "Asesor",
                                            cliente.asesor_nombre ??
                                                cliente.asesor_dni,
                                        ],
                                        [
                                            "Fecha de nacimiento",
                                            formatearFecha(
                                                cliente.fecha_nacimiento,
                                            ),
                                        ],
                                        [
                                            "Fecha de toma",
                                            formatearFecha(cliente.fecha_toma),
                                        ],
                                        [
                                            "Fecha de resultado",
                                            formatearFecha(
                                                cliente.fecha_resultado,
                                            ),
                                        ],
                                    ].map(([etiqueta, valor]) => (
                                        <div key={etiqueta}>
                                            <dt className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                                                {etiqueta}
                                            </dt>
                                            <dd className="mt-1 break-words font-semibold">
                                                {valor ?? "—"}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>

                                <div>
                                    <p className="mb-2 text-sm font-medium uppercase tracking-wide text-muted-foreground">
                                        Plazo
                                    </p>

                                    <PlazoCliente plazo={cliente.plazo} />
                                </div>
                            </div>
                        </>
                    )}
                </Dialog.Popup>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
