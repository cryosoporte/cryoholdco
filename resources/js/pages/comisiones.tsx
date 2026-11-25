import { FiltroAnio } from "@/components/comisiones/filtro-anio";
import { TablaLiquidaciones } from "@/components/comisiones/tabla-liquidaciones";
import type { ResumenLiquidacion } from "@/components/comisiones/tipos";
import { AvisoActualizacionFuente } from "@/components/reportes/aviso-actualizacion-fuente";
import type { ActualizacionFuente } from "@/components/reportes/tipos";
import AppLayout from "@/layouts/app-layout";

export default function Comisiones({
    fuente,
    puede_ver_detalle,
    liquidaciones,
    anio,
    anios,
}: {
    fuente: ActualizacionFuente | null;
    puede_ver_detalle: boolean;
    liquidaciones: ResumenLiquidacion[];
    anio: string;
    anios: string[];
}) {
    return (
        <AppLayout title="Reportes - Comisiones">
            <div className="min-w-0 space-y-6">
                <h1 className="text-2xl font-semibold tracking-tight">Comisiones</h1>
                {puede_ver_detalle && <AvisoActualizacionFuente fuente={fuente} />}
                <section className="min-w-0 space-y-3" aria-labelledby="historial-comisiones">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="historial-comisiones" className="text-lg font-semibold">Historial de liquidaciones</h2>
                        <FiltroAnio anio={anio} anios={anios} />
                    </div>
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
