<?php

namespace App\Reportes\Fuentes;

/**
 * Una fuente de datos que se carga desde un Excel y reemplaza el contenido
 * de su tabla en Oracle.
 *
 * El nombre visible y la fecha de última actualización no están aquí: viven
 * en la tabla maestra FUENTES_DATOS, en la fila cuyo CODIGO es codigo().
 */
interface Fuente
{
    /**
     * Código de la fuente en FUENTES_DATOS (por ejemplo, FACTURAS_ORACLE).
     */
    public function codigo(): string;

    /**
     * Tabla de Oracle donde se guardan las filas, con el prefijo configurado
     * en reportes.prefijo_tablas.
     */
    public function tabla(): string;

    /**
     * Columnas que debe traer el Excel, en el orden de la tabla.
     *
     * @return list<ColumnaFuente>
     */
    public function columnas(): array;
}
