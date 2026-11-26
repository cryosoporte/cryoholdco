import { HistoryIcon, KeyRoundIcon, ShieldCheckIcon, UserCogIcon } from "lucide-react";
import type { ModuloOperaciones } from "@/components/operaciones/modulos";

const modulosAdministracion: ModuloOperaciones[] = [
    {
        titulo: "Laboratorios",
        descripcion:
            "Crea el acceso de cada laboratorio al mostrador y cambia su contraseña o estado.",
        ruta: "administracion.laboratorios",
        icono: KeyRoundIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
    {
        titulo: "Usuarios",
        descripcion: "Asigna roles a las cuentas de cada persona.",
        ruta: "administracion.usuarios",
        icono: UserCogIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
    {
        titulo: "Roles",
        descripcion: "Crea roles y configura los permisos de cada uno.",
        ruta: "administracion.roles",
        icono: ShieldCheckIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
    {
        titulo: "Historial",
        descripcion: "Consulta los cambios de roles, permisos y asignaciones.",
        ruta: "administracion.historial",
        icono: HistoryIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
];

export function modulosAdministracionVisibles(rutasAdministracion: string[]) {
    return modulosAdministracion.filter((modulo) =>
        rutasAdministracion.includes(modulo.ruta),
    );
}
