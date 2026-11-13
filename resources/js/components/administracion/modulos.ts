import { KeyRoundIcon } from "lucide-react";
import type { ModuloOperaciones } from "@/components/operaciones/modulos";

/**
 * Los módulos de Administración. Para agregar uno nuevo basta con sumarlo aquí:
 * aparece en la pantalla de Administración y, con `enMenuLateral`, en el menú lateral.
 */
export const modulosAdministracion: ModuloOperaciones[] = [
    {
        titulo: "Laboratorios",
        descripcion:
            "Crea el acceso de cada laboratorio al mostrador y cambia su contraseña o estado.",
        ruta: "administracion.laboratorios",
        icono: KeyRoundIcon,
        nuevaPestana: false,
        enMenuLateral: true,
    },
];
