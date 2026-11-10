{{-- Las sedes del laboratorio elegido. Se reenvía sola cuando cambia el
     laboratorio, porque las sedes del anterior ya no valen. --}}
@foreach ($sedes as $s)
    <option value="{{ $s->codigo_sede }}" @selected($s->codigo_sede === $sede)>{{ $s->descripcion_sede }}</option>
@endforeach
