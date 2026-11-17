<?php

namespace App\Reportes\Fuentes;

/**
 * Histórico de facturas exportado de Oracle NetSuite.
 *
 * Una fila por artículo: el ID interno se repite cuando una factura tiene
 * varios artículos, por eso la tabla no tiene clave primaria.
 */
class FuenteFacturasOracle implements Fuente
{
    public const CODIGO = 'FACTURAS_ORACLE';

    public function codigo(): string
    {
        return self::CODIGO;
    }

    public function tabla(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'fuente_facturas_oracle');
    }

    /**
     * @return list<ColumnaFuente>
     */
    public function columnas(): array
    {
        return [
            ColumnaFuente::entero('ID interno', 'id_interno', 10, obligatoria: true),
            ColumnaFuente::texto('ID externo', 'id_externo', 50),
            ColumnaFuente::texto('Subsidiaria', 'subsidiaria', 150),
            ColumnaFuente::texto('N° transacción', 'numero_transaccion', 50),
            ColumnaFuente::texto('N° documento', 'numero_documento', 50),
            ColumnaFuente::texto('Estado', 'estado', 50),
            ColumnaFuente::fecha('Fecha de emisión', 'fecha_emision'),
            ColumnaFuente::texto('Cliente', 'cliente', 200),
            ColumnaFuente::texto('Nota', 'nota', 500),
            ColumnaFuente::texto('Artículo', 'articulo', 200),
            ColumnaFuente::texto('Nombre para mostrar', 'nombre_mostrar', 200),
            ColumnaFuente::texto('Servicio', 'servicio', 200),
            ColumnaFuente::texto('N° Contrato Oracle', 'numero_contrato_oracle', 50),
            ColumnaFuente::texto('N° Contrato A2in', 'numero_contrato_a2in', 50),
            ColumnaFuente::texto('PER- Tipo de Documento', 'tipo_documento', 100),
            ColumnaFuente::texto('PER- Tipo de Operación', 'tipo_operacion', 200),
            ColumnaFuente::texto('PER- Condición de Pago', 'condicion_pago', 100),
            ColumnaFuente::texto('PDF generado', 'pdf_generado', 500),
            ColumnaFuente::texto('Documento electrónico certificado', 'documento_certificado', 500),
            ColumnaFuente::texto('PER- Notas', 'notas', 1000),
            ColumnaFuente::texto('Moneda', 'moneda', 50),
            ColumnaFuente::decimal('Tipo de cambio', 'tipo_cambio', 12, 6),
            ColumnaFuente::decimal('Subtotal', 'subtotal', 14, 2),
            ColumnaFuente::decimal('Impuesto', 'impuesto', 14, 2),
            ColumnaFuente::decimal('Importe', 'importe', 14, 2),
            ColumnaFuente::decimal('Subtotal Soles', 'subtotal_soles', 14, 2),
            ColumnaFuente::decimal('Impuesto Soles', 'impuesto_soles', 14, 2),
            ColumnaFuente::decimal('Importe Soles', 'importe_soles', 14, 2),
            ColumnaFuente::texto('Creado por', 'creado_por', 150),
            ColumnaFuente::fechaHora('Fecha de creación', 'fecha_creacion'),
            ColumnaFuente::texto('Aplicación de transacción', 'aplicacion_transaccion', 200),
            ColumnaFuente::texto('Dueño', 'dueno', 150),
            ColumnaFuente::texto('Representante de ventas', 'representante_ventas', 150),
            ColumnaFuente::texto('PER- Serie', 'serie', 10),
            ColumnaFuente::texto('PER- Consecutivo', 'consecutivo', 20),
            ColumnaFuente::texto('Seguridad total', 'seguridad_total', 10),
            ColumnaFuente::fecha('Fecha de alta', 'fecha_alta'),
            ColumnaFuente::entero('CONTADOR', 'contador', 2),
            ColumnaFuente::texto('FACTURA RELACIONADA', 'factura_relacionada', 50),
            ColumnaFuente::texto('Captador Final', 'captador_final', 150),
            ColumnaFuente::texto('CANAL DE CAPTACIÓN', 'canal_captacion', 100),
            ColumnaFuente::texto('CAPTACIÓN DE ORIGEN', 'captacion_origen', 100),
            ColumnaFuente::texto('FICHA DE CAPTACIÓN', 'ficha_captacion', 100),
            ColumnaFuente::fecha('FECHA NACIMIENTO', 'fecha_nacimiento'),
            ColumnaFuente::fecha('INICIO COBERTURA', 'inicio_cobertura'),
            ColumnaFuente::fecha('FIN COBERTURA', 'fin_cobertura'),
        ];
    }
}
