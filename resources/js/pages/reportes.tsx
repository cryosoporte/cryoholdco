import { usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { AccesosModulos } from "@/components/operaciones/accesos-modulos";
import { modulosReportesVisibles } from "@/components/reportes/modulos";

export default function Reportes() {
    const { rutasReportes } = usePage().props;

    return (
        <AppLayout title="Reportes">
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Reportes</h1>
                    {/* <p className="mt-1 text-sm text-muted-foreground">
                        Selecciona un módulo para continuar.
                    </p> */}
                </div>
                <AccesosModulos modulos={modulosReportesVisibles(rutasReportes)} />
            </div>
        </AppLayout>
    );
}
