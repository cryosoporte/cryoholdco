Estimada familia {{ $cuerpo['familia'] }}:

@if ($cuerpo['plantilla'] === 'aptos')
Por medio del presente, les informamos que el procesamiento y criopreservación de las muestras correspondientes al contrato N° {{ $cuerpo['numero_base'] }} ha concluido.

Adjunto a este correo encontrarán los reportes técnicos correspondientes:

@foreach ($cuerpo['servicios'] as $servicio)
· {{ $servicio['nombre'] }}: {{ $servicio['resultado'] }}
@endforeach

Estos reportes documentan el proceso realizado y contienen la información técnica de las muestras actualmente almacenadas.

Quedamos a su disposición ante cualquier consulta.
@else
Por medio del presente, les informamos que el laboratorio ha finalizado el procesamiento de las muestras de sangre y tejido de cordón umbilical correspondientes al contrato N° {{ $cuerpo['numero_base'] }}.

Adjuntamos los reportes con los resultados de todas las pruebas realizadas para cada una de las muestras recibidas.

Los resultados obtenidos son los siguientes:

@foreach ($cuerpo['servicios'] as $servicio)
· {{ $servicio['nombre'] }}: {{ $servicio['resultado'] }}
@endforeach

La condición "NO APTO" corresponde al resultado de la evaluación realizada por el laboratorio y significa que la muestra no reúne las condiciones necesarias para su criopreservación, de acuerdo con los estándares internacionales establecidos. Este resultado no constituye un diagnóstico médico ni refleja una condición de salud del bebé o de la madre.

Si requieren información adicional o tienen alguna consulta sobre los resultados, con gusto estaremos disponibles para atenderlos.
@endif

Atentamente,
{{ $marca['nombre'] }}
