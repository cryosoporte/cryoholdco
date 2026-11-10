import type { EstadoPrueba } from "@/components/serologia/estado-prueba";

export type SedeCliente = {
    laboratorio: string;
    codigo: string;
    nombre: string;
};

export type Cliente = {
    dni: string;
    nombre: string | null;
    primer_nombre: string | null;
    numero_contrato: string;
    estado: EstadoPrueba;
    fecha_contrato: string | null;
    fecha_nacimiento: string | null;
    fecha_toma: string | null;
    fecha_resultado: string | null;
    sede_toma: string | null;
    sedes: SedeCliente[];
};
