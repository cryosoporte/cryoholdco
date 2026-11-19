import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ordenCargos } from "./configuracion";
import { DetalleAsesor } from "./detalle-asesor";
import { formatearSoles } from "./formato";
import { TablaCargo } from "./tabla-cargo";
import type { Liquidacion } from "./tipos";

export function PestanasCargos({ liquidacion }: { liquidacion: Liquidacion }) {
    const [idSeleccionado, setIdSeleccionado] = useState<string | null>(null);
    const asesor = liquidacion.asesores.find(({ id }) => id === idSeleccionado) ?? null;
    const cargos = [...liquidacion.cargos].sort(
        (primero, segundo) => ordenCargos.indexOf(primero.codigo) - ordenCargos.indexOf(segundo.codigo),
    );
    const aprobada = liquidacion.estado === "aprobada" || liquidacion.estado === "enviada_personal";

    return (
        <section className="min-w-0 space-y-3" aria-labelledby="titulo-desglose-comisiones">
            <h2 className="text-lg font-semibold" id="titulo-desglose-comisiones">Desglose por cargo</h2>
            <Tabs defaultValue={cargos[0]?.codigo} className="min-w-0">
                <div className="min-w-0 overflow-x-auto pb-1">
                    <TabsList variant="line" aria-label="Cargos" className="h-auto justify-start gap-2 px-0">
                        {cargos.map((cargo) => (
                            <TabsTrigger key={cargo.codigo} value={cargo.codigo} className="h-9 gap-2 px-2.5">
                                {cargo.etiqueta}
                                <Badge variant="secondary" className="tabular-nums">{formatearSoles(cargo.total)}</Badge>
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </div>
                {cargos.map((cargo) => (
                    <TabsContent key={cargo.codigo} value={cargo.codigo} className="min-w-0">
                        <TablaCargo cargo={cargo} onSeleccionar={setIdSeleccionado} />
                    </TabsContent>
                ))}
            </Tabs>
            <DetalleAsesor asesor={asesor} idLiquidacion={liquidacion.id} puedeVerCorreo={aprobada} onCerrar={() => setIdSeleccionado(null)} />
        </section>
    );
}
