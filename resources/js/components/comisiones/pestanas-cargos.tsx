import { useState } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ordenCargos } from "./configuracion";
import { DetalleAsesor } from "./detalle-asesor";
import { formatearSoles } from "./formato";
import { TablaCargo } from "./tabla-cargo";
import type { Liquidacion } from "./tipos";

export function PestanasCargos({ liquidacion }: { liquidacion: Liquidacion }) {
    const [idSeleccionado, setIdSeleccionado] = useState<string | null>(null);
    const asesor =
        liquidacion.asesores.find(({ id }) => id === idSeleccionado) ?? null;
    const cargos = [...liquidacion.cargos].sort(
        (primero, segundo) =>
            ordenCargos.indexOf(primero.codigo) -
            ordenCargos.indexOf(segundo.codigo),
    );
    const aprobada =
        liquidacion.estado === "aprobada" ||
        liquidacion.estado === "enviada_personal";

    return (
        <section
            className="min-w-0 space-y-3"
            aria-labelledby="titulo-desglose-comisiones"
        >
            <h2
                className="text-lg font-semibold"
                id="titulo-desglose-comisiones"
            >
                Desglose por cargo
            </h2>
            <Tabs defaultValue={cargos[0]?.codigo} className="min-w-0 gap-3">
                <TabsList
                    aria-label="Cargos"
                    className="grid w-full grid-cols-2 gap-1 rounded-xl border bg-card p-1 sm:grid-cols-3 2xl:grid-cols-6 group-data-horizontal/tabs:h-auto"
                >
                    {cargos.map((cargo) => (
                        <TabsTrigger
                            key={cargo.codigo}
                            value={cargo.codigo}
                            className="h-auto min-w-0 flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left whitespace-normal hover:bg-muted/60 data-active:bg-muted data-active:shadow-none cursor-pointer"
                        >
                            <span className="leading-snug">
                                {cargo.etiqueta}
                            </span>
                            <span className="text-xs font-normal text-muted-foreground tabular-nums">
                                {formatearSoles(cargo.total)}
                            </span>
                        </TabsTrigger>
                    ))}
                </TabsList>
                {cargos.map((cargo) => (
                    <TabsContent
                        key={cargo.codigo}
                        value={cargo.codigo}
                        className="min-w-0"
                    >
                        <TablaCargo
                            cargo={cargo}
                            onSeleccionar={setIdSeleccionado}
                        />
                    </TabsContent>
                ))}
            </Tabs>
            <DetalleAsesor
                asesor={asesor}
                idLiquidacion={liquidacion.id}
                puedeVerCorreo={aprobada}
                onCerrar={() => setIdSeleccionado(null)}
            />
        </section>
    );
}
