export type EstadoAcceso = "A" | "I";

/** Un laboratorio (por RUC) y su acceso al mostrador, si ya lo tiene. */
export type LaboratorioAcceso = {
    identificacion: string;
    nombre: string | null;
    sedes_activas: number;
    acceso: {
        id: number;
        estado: EstadoAcceso;
    } | null;
};

export const opcionesEstadoAcceso: { value: EstadoAcceso; label: string }[] = [
    { value: "A", label: "Activo" },
    { value: "I", label: "Inactivo" },
];

export type RolSistema = "ADMINISTRADOR" | "GERENCIA";

export type PersonaUsuario = {
    id: number;
    nombre: string;
    correos: string[];
    dni: string;
    cuentas: number;
    rol: RolSistema | null;
    rol_incompleto: boolean;
    es_usted: boolean;
};

export const opcionesRol = {
    "sin-rol": {
        value: "sin-rol",
        label: "Sin rol",
        descripcion: "Conserva los permisos básicos del sistema.",
    },
    ADMINISTRADOR: {
        value: "ADMINISTRADOR",
        label: "Administrador",
        descripcion:
            "Administra usuarios y laboratorios, procesa y envía liquidaciones de comisiones.",
    },
    GERENCIA: {
        value: "GERENCIA",
        label: "Gerencia",
        descripcion: "Aprueba o rechaza liquidaciones de comisiones.",
    },
} as const;

export type FiltroRolUsuario = keyof typeof opcionesRol | "todos";
export type ConteosPorRol = Record<FiltroRolUsuario, number>;
