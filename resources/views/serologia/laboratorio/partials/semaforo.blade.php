{{--
    El semáforo del mostrador. Una sola cosa a la vez y en letra grande, que
    se lee de pie y con la clienta delante.

    Cuatro casos:
      1. error ........... el código no es de Criocord, o no tiene toma
      2. recién marcada .. acaba de pulsar el botón
      3. ya fue tomada ... no repetir
      4. puede tomarse ... con el botón para marcarla
--}}

@if ($error)
    <div role="alert" class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-5 text-red-900">
        <svg class="mt-0.5 size-6 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9.25" stroke="currentColor" stroke-width="1.6" opacity=".35" />
            <path d="M12 7.5V13m0 3.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <p class="text-base font-semibold text-pretty">{{ $error }}</p>
    </div>

@elseif ($contrato && $toma)
    @php
        $yaTomada = $toma->estado() !== 'pendiente';

        [$tono, $lampara] = match (true) {
            $recienMarcada => ['border-emerald-300 bg-emerald-50 text-emerald-900', 'bg-emerald-500'],
            $yaTomada => ['border-amber-300 bg-amber-50 text-amber-900', 'bg-amber-500'],
            default => ['border-criocord-300 bg-criocord-50 text-criocord-900', 'bg-criocord-500'],
        };
    @endphp

    <div role="status" class="rounded-2xl border p-5 shadow-sm sm:p-6 {{ $tono }}">
        <div class="flex items-start gap-4">
            <span class="mt-1 size-4 shrink-0 rounded-full {{ $lampara }}" aria-hidden="true"></span>

            <div class="min-w-0 space-y-1">
                <p class="text-lg font-bold tracking-tight sm:text-xl">{{ $contrato->nombre_completo }}</p>

                @if ($recienMarcada)
                    <p class="text-base font-semibold">
                        Muestra tomada · {{ $toma->fecha_toma?->format('H:i') }}
                    </p>
                    <p class="text-sm opacity-90">Listo. Criocord ya la ve en su tablero.</p>

                @elseif ($yaTomada)
                    <p class="text-base font-semibold">Ya fue tomada</p>
                    <p class="text-sm text-pretty opacity-90">
                        El {{ $toma->fecha_toma?->format('d/m/Y') }}
                        a las {{ $toma->fecha_toma?->format('H:i') }}
                        @if ($toma->sede_toma)
                            en {{ \App\Models\Sede::nombre($toma->laboratorio_toma, $toma->sede_toma) ?: 'otra sede' }}
                        @endif
                        . No repetir.
                    </p>

                @else
                    <p class="text-base font-semibold">Puede tomarse</p>
                    <p class="text-sm text-pretty opacity-90">
                        Verifica que el DNI físico sea de {{ $contrato->primer_nombre }}.
                    </p>
                @endif

                <p class="pt-1 font-mono text-xs opacity-70">
                    {{ $toma->numero_contrato_base }}
                    @if ($toma->numero_toma > 1)
                        · toma {{ $toma->numero_toma }}
                    @endif
                </p>
            </div>
        </div>

        @if (! $yaTomada && ! $recienMarcada)
            {{-- Sin JavaScript este botón manda un POST normal y la página se
                 vuelve a pintar entera; con JavaScript lo intercepta el fetch. --}}
            <form method="POST" action="{{ route('laboratorio.marcar') }}" data-form-marcar class="mt-5">
                @csrf
                <input type="hidden" name="codigo" value="{{ $toma->numero_contrato_base }}">

                <button type="submit"
                        class="w-full rounded-xl bg-criocord-600 px-6 py-4 text-base font-semibold text-white shadow-sm transition hover:bg-criocord-700 focus:ring-3 focus:ring-criocord-200 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60">
                    Muestra tomada
                </button>
            </form>
        @endif
    </div>
@endif
