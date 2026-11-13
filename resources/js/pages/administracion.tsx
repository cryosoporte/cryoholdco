import AppLayout from "@/layouts/app-layout";
import { AccesosModulos } from "@/components/operaciones/accesos-modulos";
import { modulosAdministracion } from "@/components/administracion/modulos";

export default function Administracion() {
    return (
        <AppLayout title="Administración">
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Administración</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Selecciona un módulo para continuar.
                    </p>
                </div>
                <AccesosModulos modulos={modulosAdministracion} />
            </div>
        </AppLayout>
    );
}
