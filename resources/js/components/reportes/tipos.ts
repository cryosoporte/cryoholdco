/**
 * Una fuente de datos y su última carga, tal como la envía
 * FuenteDatos::datosActualizacion(). Si nunca se cargó, los datos de la
 * carga llegan en null.
 */
export type FuenteDatos = {
    codigo: string;
    nombre: string;
    /** ISO 8601 en hora de Lima, por ejemplo 2026-11-17T10:32:00-05:00. */
    fecha_actualizacion: string | null;
    actualizado_por: string | null;
    filas: number | null;
    archivo: string | null;
    ultima_carga: CargaFuente | null;
};

export type CargaFuente = {
    estado: "P" | "E" | "C" | "F";
    filas: number | null;
    mensaje: string | null;
};

export function hayCargaActiva(fuente: FuenteDatos): boolean {
    const estado = fuente.ultima_carga?.estado;

    return estado === "P" || estado === "E";
}

export type ActualizacionFuente = Pick<
    FuenteDatos,
    "codigo" | "nombre" | "fecha_actualizacion" | "actualizado_por"
>;
