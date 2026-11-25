import { useState } from "react";
import { DialogoEliminarRol } from "@/components/administracion/dialogo-eliminar-rol";
import { DialogoGuardarRol } from "@/components/administracion/dialogo-guardar-rol";
import { TablaRoles } from "@/components/administracion/tabla-roles";
import type { RolResumen } from "@/components/administracion/tipos";
import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/app-layout";

type Dialogo = { tipo: "crear" } | { tipo: "editar" | "eliminar"; rol: RolResumen };

export default function AdministracionRoles({ rolesRegistrados }: { rolesRegistrados: RolResumen[] }) {
    const [dialogo, setDialogo] = useState<Dialogo | null>(null);
    const cerrarDialogo = () => setDialogo(null);

    return (
        <AppLayout title="Administración - Roles">
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-2xl font-semibold">Roles</h1>
                    <Button type="button" onClick={() => setDialogo({ tipo: "crear" })}>Nuevo rol</Button>
                </div>
                <TablaRoles
                    roles={rolesRegistrados}
                    onEditar={(rol) => setDialogo({ tipo: "editar", rol })}
                    onEliminar={(rol) => setDialogo({ tipo: "eliminar", rol })}
                />
            </div>
            {dialogo?.tipo === "crear" && <DialogoGuardarRol rol={null} onCerrar={cerrarDialogo} />}
            {dialogo?.tipo === "editar" && <DialogoGuardarRol rol={dialogo.rol} onCerrar={cerrarDialogo} />}
            {dialogo?.tipo === "eliminar" && <DialogoEliminarRol rol={dialogo.rol} onCerrar={cerrarDialogo} />}
        </AppLayout>
    );
}
