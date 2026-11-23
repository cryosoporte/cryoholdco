import { useState } from "react";
import type { ConteosPorRol, FiltroRolUsuario, PersonaUsuario } from "./tipos";

function normalizarBusqueda(texto: string): string {
    return texto.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLowerCase();
}

export function useFiltroUsuarios(personas: PersonaUsuario[]) {
    const [busqueda, setBusqueda] = useState("");
    const [filtroRol, setFiltroRol] = useState<FiltroRolUsuario>("todos");
    const termino = normalizarBusqueda(busqueda.trim());
    const conteosPorRol: ConteosPorRol = {
        todos: personas.length,
        ADMINISTRADOR: 0,
        GERENCIA: 0,
        "sin-rol": 0,
    };

    for (const persona of personas) {
        conteosPorRol[persona.rol ?? "sin-rol"]++;
    }

    const personasVisibles = personas.filter((persona) => {
        if (filtroRol !== "todos" && filtroRol !== (persona.rol ?? "sin-rol")) {
            return false;
        }

        return (
            termino === "" ||
            [persona.nombre, ...persona.correos, persona.dni].some((campo) =>
                normalizarBusqueda(campo).includes(termino),
            )
        );
    });

    return {
        busqueda,
        setBusqueda,
        filtroRol,
        setFiltroRol,
        conteosPorRol,
        personasVisibles,
    };
}
