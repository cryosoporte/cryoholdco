{{-- Lo tomado hoy en esta sede. Solo hora y nombre. --}}

<div>
    <span class="text-xs font-semibold tracking-wider text-slate-400 uppercase">
        Hoy · {{ $nombreSede ?: 'sin sede' }}
    </span>
    <h2 class="mt-1 text-lg font-bold tracking-tight text-slate-900">Muestras tomadas</h2>
</div>

@if ($hoy->isEmpty())
    <p class="mt-4 text-sm text-slate-500">Aún no hay muestras tomadas hoy en esta sede.</p>
@else
    <ul class="mt-4 divide-y divide-slate-100">
        @foreach ($hoy as $fila)
            <li class="flex items-baseline gap-3 py-3 first:pt-0">
                <span class="shrink-0 font-mono text-sm font-semibold text-slate-900 tabular-nums">
                    {{ \Illuminate\Support\Carbon::parse($fila->fecha_toma)->format('H:i') }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-slate-800">
                        {{ $fila->nombre_completo ?: 'Sin nombre registrado' }}
                    </span>
                    <span class="block truncate font-mono text-xs text-slate-400">
                        {{ $fila->numero_contrato_base }}
                    </span>
                </span>
            </li>
        @endforeach
    </ul>

    <p class="mt-4 border-t border-slate-100 pt-3 text-sm font-medium text-slate-600">
        {{ $hoy->count() }} {{ $hoy->count() === 1 ? 'muestra' : 'muestras' }} hoy
    </p>
@endif

<p class="mt-5 border-t border-slate-100 pt-4 text-xs text-pretty text-slate-500">
    Esta pantalla solo muestra el nombre y el semáforo. No muestra teléfono,
    fecha de parto, clínica ni resultados.
</p>
