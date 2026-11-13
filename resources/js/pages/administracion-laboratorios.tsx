import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { DialogoAcceso } from "@/components/administracion/dialogo-acceso";
import { TablaLaboratorios } from "@/components/administracion/tabla-laboratorios";
import type { LaboratorioAcceso } from "@/components/administracion/tipos";

export default function AdministracionLaboratorios({
    laboratorios,
}: {
    laboratorios: LaboratorioAcceso[];
}) {
    const [seleccionado, setSeleccionado] = useState<LaboratorioAcceso | null>(
        null,
    );

    return (
        <AppLayout title="Administración - Laboratorios">
            <div>
                <h1 className="text-2xl font-semibold">
                    Accesos de laboratorios
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Cada laboratorio entra al mostrador con su RUC y la
                    contraseña que le asignes aquí. Solo pueden ingresar los
                    que tienen sedes activas.
                </p>
            </div>

            <TablaLaboratorios
                laboratorios={laboratorios}
                onSeleccionar={setSeleccionado}
            />

            <DialogoAcceso
                laboratorio={seleccionado}
                onCerrar={() => setSeleccionado(null)}
            />
        </AppLayout>
    );
}
