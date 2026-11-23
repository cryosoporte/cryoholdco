import { KeyRoundIcon, UserCogIcon } from "lucide-react";
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
        descripcion: "Asigna o quita el rol de Administrador o Gerencia de cada persona.",
        ruta: "administracion.usuarios",
        icono: UserCogIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
];

export function modulosAdministracionVisibles(rutasAdministracion: string[]) {
    return modulosAdministracion.filter((modulo) =>
        rutasAdministracion.includes(modulo.ruta),
    );
}
