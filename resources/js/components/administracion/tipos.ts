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

export type RolDisponible = {
    codigo: string;
    nombre: string;
    descripcion: string | null;
};

export type RolResumen = RolDisponible & {
    es_sistema: boolean;
    personas: number;
    permisos: number;
    motivo_no_eliminar: string | null;
};

export type RolDetalle = Omit<RolResumen, "permisos"> & {
    permisos: string[];
    obsoletos: string[];
};

export type ModuloPermisos = {
    modulo: string;
    permisos: {
        codigo: string;
        nombre: string;
        descripcion: string;
        fijo: boolean;
        /** Solo lo puede tener el rol Administrador. */
        exclusivo: boolean;
    }[];
};

export type PersonaConRol = {
    nombre: string;
    correos: string[];
};

export type PersonaUsuario = {
    id: number;
    nombre: string;
    correos: string[];
    dni: string;
    cuentas: number;
    roles: string[];
    roles_incompletos: boolean;
    es_usted: boolean;
};

export type EventoSeguridad = {
    id: number;
    fecha: string;
    autor: string;
    empresa: string | null;
    tipo: string;
    accion: string;
    referencia: string | null;
    detalle: { etiqueta: string; valor: string }[];
};
