import { useState } from "react";
import type { PersonaUsuario, RolDisponible } from "./tipos";

function normalizarBusqueda(texto: string): string {
    return texto.normalize("NFD").replace(/\p{Diacritic}/gu, "").toLowerCase();
}

export function useFiltroUsuarios(personas: PersonaUsuario[], rolesDisponibles: RolDisponible[]) {
    const [busqueda, setBusqueda] = useState("");
    const [rolSeleccionado, setFiltroRol] = useState("todos");
    const termino = normalizarBusqueda(busqueda.trim());
    const conteosPorRol = new Map<string, number>();
    let sinRol = 0;

    for (const persona of personas) {
        if (persona.roles.length === 0) {
            sinRol++;
        }
        for (const rol of persona.roles) {
            conteosPorRol.set(rol, (conteosPorRol.get(rol) ?? 0) + 1);
        }
    }

    const opcionesRol = [
        { value: "todos", label: `Todos (${personas.length})` },
        ...rolesDisponibles.map(({ codigo, nombre }) => ({
            value: codigo,
            label: `${nombre} (${conteosPorRol.get(codigo) ?? 0})`,
        })),
        { value: "sin-rol", label: `Sin rol (${sinRol})` },
    ];
    const filtroRol = opcionesRol.some(({ value }) => value === rolSeleccionado) ? rolSeleccionado : "todos";

    const personasVisibles = personas.filter((persona) => {
        const coincideRol = filtroRol === "todos" ||
            (filtroRol === "sin-rol" && persona.roles.length === 0) ||
            persona.roles.includes(filtroRol);
        if (!coincideRol) {
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
        opcionesRol,
        personasVisibles,
    };
}
