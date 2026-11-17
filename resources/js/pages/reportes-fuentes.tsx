import { usePoll } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { TarjetaFuente } from "@/components/reportes/tarjeta-fuente";
import { hayCargaActiva, type FuenteDatos } from "@/components/reportes/tipos";

function ConsultarCargasActivas() {
    usePoll(3000, { only: ["fuentes"] });

    return null;
}

export default function ReportesFuentes({
    fuentes,
}: {
    fuentes: FuenteDatos[];
}) {
    return (
        <AppLayout title="Reportes - Fuentes de datos">
            {fuentes.some(hayCargaActiva) && <ConsultarCargasActivas />}
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Fuentes de datos</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Los reportes se generan con los datos de estas
                        fuentes. Sube el .xlsx exportado de NetSuite para
                        actualizarlas.
                    </p>
                </div>

                {fuentes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No hay fuentes registradas.
                    </p>
                ) : (
                    <div className="grid max-w-2xl gap-4">
                        {fuentes.map((fuente) => (
                            <TarjetaFuente key={fuente.codigo} fuente={fuente} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
