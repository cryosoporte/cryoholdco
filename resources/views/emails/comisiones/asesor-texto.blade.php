Hola, {!! $asesor['nombre_corto'] !!}:

@if ($asesor['total'] > 0)
Tu comisión de {!! $periodo !!} fue aprobada por Gerencia. Este es el detalle por línea:
@else
Te compartimos el resultado de {!! $periodo !!}. Este mes no se generó comisión; este es tu avance frente a cada cuota:
@endif

Concepto | Logrado | Cuota | Tramo aplicado | Comisión S/
@foreach ($asesor['lineas'] as $linea)
{!! $linea['concepto'] !!} | {!! $linea['logrado'] !!} | {!! $linea['cuota'] !!} | {!! $linea['tramo'] !!} | S/ {{ number_format($linea['comision'], 2) }}
@endforeach
Total {!! $periodo !!}: S/ {{ number_format($asesor['total'], 2) }}

{!! $asesor['nota'] !!}

Aprobación del período: {{ $fechaAprobacion }}. El pago se realizará con la planilla del mes.
Ante cualquier consulta sobre este detalle, responde a este correo. Este correo se generó automáticamente.
