import { CodigoQr } from "./codigo-qr";
import { EstadoConsulta } from "./estado-consulta";
import { AvancePrueba } from "./avance-prueba";
import { SedesDisponibles } from "./sedes-disponibles";
import type { Cliente } from "./tipos";

export function FichaCliente({ cliente }: { cliente: Cliente }) {
    const mostrarSedes =
        cliente.estado === "pendiente" && cliente.sedes.length > 0;
    return (
        <section className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-3 border-b pb-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Hola, {cliente.primer_nombre ?? "clienta Criocord"}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {cliente.nombre}
                    </p>
                </div>
                <p className="text-sm text-muted-foreground">
                    Contrato{" "}
                    <span className="font-mono text-foreground">
                        {cliente.numero_contrato}
                    </span>
                </p>
            </div>
            <div
                className={`grid items-start gap-5 md:grid-cols-2 ${mostrarSedes ? "xl:grid-cols-3" : "lg:grid-cols-[22rem_minmax(0,1fr)]"}`}
            >
                <CodigoQr numeroContrato={cliente.numero_contrato} />
                <div className="space-y-5">
                    <EstadoConsulta estado={cliente.estado} />
                    <AvancePrueba cliente={cliente} />
                </div>
                {mostrarSedes && (
                    <div className="md:col-span-2 xl:col-span-1">
                        <SedesDisponibles sedes={cliente.sedes} />
                    </div>
                )}
            </div>
        </section>
    );
}
