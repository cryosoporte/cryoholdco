import { ClipboardListIcon, DatabaseIcon, type LucideIcon } from "lucide-react";

export type ModuloReportes = {
    titulo: string;
    descripcion: string;
    ruta: string;
    icono: LucideIcon;
    nuevaPestana: boolean;
    enMenuLateral: boolean;
};

export const modulosReportes: ModuloReportes[] = [
    {
        titulo: "Comisiones",
        descripcion: "Reporte de comisiones",
        ruta: "reportes.comisiones",
        icono: ClipboardListIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
    {
        titulo: "Fuentes de datos",
        descripcion: "Carga los archivos de NetSuite que usan los reportes.",
        ruta: "reportes.fuentes",
        icono: DatabaseIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
];
