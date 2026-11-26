import { usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { AccesosModulos } from "@/components/operaciones/accesos-modulos";
import { modulosAdministracionVisibles } from "@/components/administracion/modulos";

export default function Administracion() {
    const { rutasAdministracion } = usePage().props;

    return (
        <AppLayout title="Administración">
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Administración</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Selecciona un módulo para continuar.
                    </p>
                </div>
                <AccesosModulos
                    modulos={modulosAdministracionVisibles(rutasAdministracion)}
                />
            </div>
        </AppLayout>
    );
}
