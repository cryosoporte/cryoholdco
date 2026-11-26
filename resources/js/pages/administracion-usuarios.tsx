import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { DialogoRoles } from "@/components/administracion/dialogo-roles";
import { FiltrosUsuarios } from "@/components/administracion/filtros-usuarios";
import { TablaUsuarios } from "@/components/administracion/tabla-usuarios";
import { useFiltroUsuarios } from "@/components/administracion/use-filtro-usuarios";
import type { PersonaUsuario, RolDisponible } from "@/components/administracion/tipos";

export default function AdministracionUsuarios({
    personas,
    rolesDisponibles,
}: {
    personas: PersonaUsuario[];
    rolesDisponibles: RolDisponible[];
}) {
    const [seleccionada, setSeleccionada] = useState<PersonaUsuario | null>(null);
    const {
        busqueda,
        setBusqueda,
        filtroRol,
        setFiltroRol,
        opcionesRol,
        personasVisibles,
    } = useFiltroUsuarios(personas, rolesDisponibles);

    return (
        <AppLayout title="Administración - Usuarios">
            <div>
                <h1 className="text-2xl font-semibold">Usuarios</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Los roles definen los permisos de cada persona y se aplican a todas sus cuentas.
                </p>
            </div>
            <FiltrosUsuarios
                busqueda={busqueda}
                onCambiarBusqueda={setBusqueda}
                filtroRol={filtroRol}
                onCambiarRol={setFiltroRol}
                opcionesRol={opcionesRol}
                totalVisible={personasVisibles.length}
                totalPersonas={personas.length}
            />
            <TablaUsuarios
                personas={personasVisibles}
                rolesDisponibles={rolesDisponibles}
                onSeleccionar={setSeleccionada}
            />
            {seleccionada && (
                <DialogoRoles
                    key={seleccionada.id}
                    persona={seleccionada}
                    rolesDisponibles={rolesDisponibles}
                    onCerrar={() => setSeleccionada(null)}
                />
            )}
        </AppLayout>
    );
}
