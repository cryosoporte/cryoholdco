@php
  $parrafo = 'margin:0 0 13px 0; text-align:justify;';
  $lista = 'margin:0 0 13px 0; padding-left:20px;';
@endphp
<p style="{{ $parrafo }} font-weight:600;">Estimada familia {{ $cuerpo['familia'] }}:</p>

@if ($cuerpo['plantilla'] === 'aptos')
  <p style="{{ $parrafo }}">
    Por medio del presente, les informamos que el procesamiento y criopreservación de las muestras
    correspondientes al contrato N° <strong>{{ $cuerpo['numero_base'] }}</strong> ha concluido.
  </p>
  <p style="{{ $parrafo }}">Adjunto a este correo encontrarán los reportes técnicos correspondientes:</p>
  <ul style="{{ $lista }}">
    @foreach ($cuerpo['servicios'] as $servicio)
      <li>{{ $servicio['nombre'] }}: {{ $servicio['resultado'] }}</li>
    @endforeach
  </ul>
  <p style="{{ $parrafo }}">
    Estos reportes documentan el proceso realizado y contienen la información técnica de las
    muestras actualmente almacenadas.
  </p>
  <p style="{{ $parrafo }}">Quedamos a su disposición ante cualquier consulta.</p>
@else
  <p style="{{ $parrafo }}">
    Por medio del presente, les informamos que el laboratorio ha finalizado el procesamiento de las
    muestras de sangre y tejido de cordón umbilical correspondientes al contrato N°
    <strong>{{ $cuerpo['numero_base'] }}</strong>.
  </p>
  <p style="{{ $parrafo }}">
    Adjuntamos los reportes con los resultados de todas las pruebas realizadas para cada una de las
    muestras recibidas.
  </p>
  <p style="{{ $parrafo }}">Los resultados obtenidos son los siguientes:</p>
  <ul style="{{ $lista }}">
    @foreach ($cuerpo['servicios'] as $servicio)
      <li>{{ $servicio['nombre'] }}: {{ $servicio['resultado'] }}</li>
    @endforeach
  </ul>
  <p style="{{ $parrafo }}">
    La condición "NO APTO" corresponde al resultado de la evaluación realizada por el laboratorio y
    significa que la muestra no reúne las condiciones necesarias para su criopreservación, de
    acuerdo con los estándares internacionales establecidos. Este resultado no constituye un
    diagnóstico médico ni refleja una condición de salud del bebé o de la madre.
  </p>
  <p style="{{ $parrafo }}">
    Si requieren información adicional o tienen alguna consulta sobre los resultados, con gusto
    estaremos disponibles para atenderlos.
  </p>
@endif

<p style="margin:0; text-align:left;">Atentamente,<br><strong>{{ $marca['nombre'] }}</strong></p>
