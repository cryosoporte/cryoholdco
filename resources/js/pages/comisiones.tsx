import { AvisoActualizacionFuente } from "@/components/reportes/aviso-actualizacion-fuente";
import type { ActualizacionFuente } from "@/components/reportes/tipos";
import AppLayout from "@/layouts/app-layout";

export default function Comisiones({
    fuente,
}: {
    fuente: ActualizacionFuente | null;
}) {
    return (
        <AppLayout title="Reportes - Comisiones">
            <div className="space-y-6">
                <h1 className="text-2xl font-semibold">Comisiones</h1>
                <AvisoActualizacionFuente fuente={fuente} />
            </div>
        </AppLayout>
    );
}
