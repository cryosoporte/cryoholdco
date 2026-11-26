import type { EstadoPrueba } from "@/components/serologia/estado-prueba";

export type OpcionLaboratorio = {
    codigo: string;
    nombre: string;
    direccion: string | null;
    ciudad: string | null;
};

export type ResultadoLaboratorio = {
    nombre: string | null;
    primer_nombre: string | null;
    numero_contrato: string;
    numero_toma: number;
    estado: Exclude<EstadoPrueba, "sin_registro">;
    fecha_toma: string | null;
    hora_toma: string | null;
    sede_toma: string | null;
    recien_marcada: boolean;
};

export type TomaDelDia = {
    numero_contrato: string;
    numero_toma: number;
    nombre: string | null;
    hora: string;
};

export type LaboratorioProps = {
    laboratorios: OpcionLaboratorio[];
    sedes: OpcionLaboratorio[];
    laboratorio: string | null;
    sede: string | null;
    nombre_sede: string | null;
    hoy: TomaDelDia[];
    resultado: ResultadoLaboratorio | null;
};

/** Props de la pantalla donde el laboratorio elige la sede antes de entrar al mostrador. */
export type EleccionSedeProps = {
    sedes: OpcionLaboratorio[];
    /** RUC del laboratorio que inició sesión. */
    laboratorio: string;
    nombre_laboratorio: string | null;
    sede: string | null;
};
