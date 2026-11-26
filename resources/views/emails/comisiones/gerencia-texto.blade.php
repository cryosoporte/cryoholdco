Hola, Gerencia:

Se procesaron las comisiones de {!! $liquidacion['periodo'] !!} con la base {!! $liquidacion['base']['fuente'] !!} ({{ number_format($liquidacion['base']['filas']) }} filas). El total a pagar es S/ {{ number_format($liquidacion['total'], 2) }} para {{ $liquidacion['colaboradores_con_comision'] }} de {{ $liquidacion['colaboradores'] }} colaboradores.

Cargo | Con comisión | Total S/
@foreach ($liquidacion['resumen_cargos'] as $cargo)
{!! $cargo['cargo'] !!} | {{ $cargo['con_comision'] }} de {{ $cargo['colaboradores'] }} | S/ {{ number_format($cargo['total'], 2) }}
@endforeach
Total | {{ $liquidacion['colaboradores_con_comision'] }} de {{ $liquidacion['colaboradores'] }} | S/ {{ number_format($liquidacion['total'], 2) }}

Puntos a revisar
@foreach ($liquidacion['puntos_revision'] as $punto)
- {!! $punto !!}
@endforeach

Revisar y aprobar en el sistema:
{!! $enlaceDetalle !!}

Procesado por {!! $procesadoPor !!} el {{ $fechaProceso }}. Este correo se generó automáticamente.
