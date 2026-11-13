import {
    ClipboardListIcon,
    FlaskConicalIcon,
    UsersIcon,
    type LucideIcon,
} from "lucide-react";

/**
 * Un módulo de Operaciones. Para agregar uno nuevo basta con sumarlo a
 * `modulosOperaciones`: aparece en la pantalla de Operaciones y, si es
 * interno, también en el menú lateral.
 */
export type ModuloOperaciones = {
    titulo: string;
    descripcion: string;
    /** Nombre de la ruta de Laravel (Ziggy). */
    ruta: string;
    icono: LucideIcon;
    /** Las pantallas públicas (para clientes o laboratorios) se abren en otra pestaña. */
    nuevaPestana: boolean;
    /** Si se muestra bajo "Operaciones" en el menú lateral. */
    enMenuLateral: boolean;
};

export const modulosOperaciones: ModuloOperaciones[] = [
    {
        titulo: "Serología",
        descripcion: "Consulta estados, plazos y resultados de los clientes.",
        ruta: "serologia",
        icono: ClipboardListIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
    {
        titulo: "Clientes",
        descripcion:
            "Consulta una prueba por DNI y muestra el código QR del contrato.",
        ruta: "clientes.index",
        icono: UsersIcon,
        nuevaPestana: true,
        enMenuLateral: false,
    },
    {
        titulo: "Laboratorio",
        descripcion:
            "Mostrador de los laboratorios: ingresan con su RUC para registrar muestras.",
        ruta: "laboratorio.login",
        icono: FlaskConicalIcon,
        nuevaPestana: true,
        enMenuLateral: false,
    },
];
