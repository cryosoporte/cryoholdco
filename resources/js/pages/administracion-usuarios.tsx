import { useState } from "react";
import AppLayout from "@/layouts/app-layout";
import { DialogoRol } from "@/components/administracion/dialogo-rol";
import { FiltrosUsuarios } from "@/components/administracion/filtros-usuarios";
import { TablaUsuarios } from "@/components/administracion/tabla-usuarios";
import { useFiltroUsuarios } from "@/components/administracion/use-filtro-usuarios";
import type { PersonaUsuario } from "@/components/administracion/tipos";

export default function AdministracionUsuarios({
    personas,
}: {
    personas: PersonaUsuario[];
}) {
    const [seleccionada, setSeleccionada] = useState<PersonaUsuario | null>(null);
    const {
        busqueda,
        setBusqueda,
        filtroRol,
        setFiltroRol,
        conteosPorRol,
        personasVisibles,
    } = useFiltroUsuarios(personas);

    return (
        <AppLayout title="Administración - Usuarios">
            <div>
                <h1 className="text-2xl font-semibold">Usuarios</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    El rol define qué puede hacer cada persona en el sistema. Se
                    aplica a la vez en CRIOCORD y LAZO DE VIDA.
                </p>
            </div>
            <FiltrosUsuarios
                busqueda={busqueda}
                onCambiarBusqueda={setBusqueda}
                filtroRol={filtroRol}
                onCambiarRol={setFiltroRol}
                conteosPorRol={conteosPorRol}
                totalVisible={personasVisibles.length}
            />
            <TablaUsuarios
                personas={personasVisibles}
                onSeleccionar={setSeleccionada}
            />
            {seleccionada && (
                <DialogoRol
                    key={seleccionada.id}
                    persona={seleccionada}
                    onCerrar={() => setSeleccionada(null)}
                />
            )}
        </AppLayout>
    );
}
