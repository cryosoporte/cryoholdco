<?php

namespace App\Reportes\Comisiones;

/**
 * Bono pronta venta, regla provisional indicada por la gerencia: el ejecutivo
 * de ventas que tiene al menos MINIMO_CONTRATOS contratos UCU emitidos hasta
 * el DIA_LIMITE del mes cobra un bono fijo de MONTO. La gerencia lo define mes
 * a mes; con MINIMO_CONTRATOS en null no hay bono: no se calcula ni se muestra.
 *
 * Solo se paga en los meses: no entra en el trimestre ni se descuenta como
 * adelanto. Los contratos se atribuyen al representante de ventas. No está en
 * COM_META porque la tabla no guarda el día de corte; se evalúa como una meta
 * de monto fijo para mostrarse junto a las demás.
 */
class BonoProntaVenta
{
    public const LINEA = 'PRONTA_VENTA';

    public const ROL = 'EJECUTIVO DE VENTAS';

    /** Línea cuyos contratos cuentan para el bono. */
    public const LINEA_VENTAS = 'UCU';

    /**
     * CUOTA DEL BONO PRONTA VENTA: contratos UCU que debe tener el ejecutivo
     * hasta DIA_LIMITE. Se cambia aquí cuando la gerencia la confirma (por
     * ejemplo, 10). En null no hay bono y la columna no aparece.
     */
    public const ?int MINIMO_CONTRATOS = null;

    /** Último día del mes, inclusive, en que la emisión cuenta. */
    public const DIA_LIMITE = 24;

    public const MONTO = 500.0;

    /**
     * @param  ?int  $minimoContratos  la cuota vigente; las pruebas pasan otra
     */
    public function __construct(public ?int $minimoContratos = self::MINIMO_CONTRATOS) {}

    public function activo(): bool
    {
        return $this->minimoContratos !== null;
    }

    /**
     * La meta de monto fijo con la que se evalúa el bono. Solo con el bono
     * activo.
     */
    public function meta(): MetaComision
    {
        $minimo = (float) $this->minimoContratos;

        return new MetaComision(
            id: 0,
            rol: self::ROL,
            atribucion: Atribucion::Representante,
            linea: self::LINEA,
            periodicidad: Periodicidad::Mensual,
            tipo: TipoMeta::MontoFijo,
            baseTramo: BaseTramo::Cantidad,
            minimo: $minimo,
            tramos: [new TramoMeta(1, $minimo, null, self::MONTO)],
        );
    }

    public static function cuenta(ContratoComisionable $contrato): bool
    {
        return $contrato->linea === self::LINEA_VENTAS
            && (int) substr($contrato->fechaEmision, 8, 2) <= self::DIA_LIMITE;
    }
}
