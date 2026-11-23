import { DialogoNuevaLiquidacion } from "@/components/comisiones/dialogo-nueva-liquidacion";
import { TablaLiquidaciones } from "@/components/comisiones/tabla-liquidaciones";
import type { ResumenLiquidacion } from "@/components/comisiones/tipos";
import { AvisoActualizacionFuente } from "@/components/reportes/aviso-actualizacion-fuente";
import type { ActualizacionFuente } from "@/components/reportes/tipos";
import AppLayout from "@/layouts/app-layout";

export default function Comisiones({
    fuente,
    liquidaciones,
    puede_elegir_periodo,
    periodos_disponibles,
}: {
    fuente: ActualizacionFuente | null;
    liquidaciones: ResumenLiquidacion[];
    puede_elegir_periodo: boolean;
    periodos_disponibles: { id: string; etiqueta: string }[];
}) {
    return (
        <AppLayout title="Reportes - Comisiones">
            <div className="min-w-0 space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold tracking-tight">Comisiones</h1>
                    {puede_elegir_periodo && <DialogoNuevaLiquidacion periodos={periodos_disponibles} />}
                </div>
                <AvisoActualizacionFuente fuente={fuente} />
                <section className="min-w-0 space-y-3" aria-labelledby="historial-comisiones">
                    <h2 id="historial-comisiones" className="text-lg font-semibold">Historial de liquidaciones</h2>
                    {liquidaciones.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                            Aún no hay liquidaciones.
                        </p>
                    ) : <TablaLiquidaciones liquidaciones={liquidaciones} />}
                </section>
            </div>
        </AppLayout>
    );
}
