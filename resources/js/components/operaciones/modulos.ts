import {
    ClipboardListIcon,
    FlaskConicalIcon,
    UsersIcon,
    type LucideIcon,
} from "lucide-react";

export type ModuloOperaciones = {
    titulo: string;
    descripcion: string;
    ruta: string;
    icono: LucideIcon;
    nuevaPestana: boolean;
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
