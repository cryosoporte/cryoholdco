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
