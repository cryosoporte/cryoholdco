import AppLayout from "@/layouts/app-layout";
import { AccesosModulos } from "@/components/operaciones/accesos-modulos";

export default function Operaciones() {
    return (
        <AppLayout title="Operaciones">
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Operaciones</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Selecciona un módulo para continuar.
                    </p>
                </div>
                <AccesosModulos />
            </div>
        </AppLayout>
    );
}
