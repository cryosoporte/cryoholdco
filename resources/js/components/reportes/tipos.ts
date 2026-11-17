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
};
