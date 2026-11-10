@php
    // Todo lo que se ve aquí sale de la toma (OPE_TOMAS_SEROLOGICAS), no del
    // contrato. Si no hay toma, $toma es null y el estado es 'sin_registro'.
    $toma = $contrato->tomaActiva();
    $estado = $contrato->estado();

    $fechaToma = $toma?->fecha_toma;
    $fechaResultado = $toma?->fecha_resultado;

    // Los estados del proceso. Es texto de pantalla, por eso vive aquí.
    // [clases de color, trazo del icono, título, mensaje]
    [$tono, $icono, $titulo, $mensaje] = match ($estado) {
        'recibido' => [
            'border-emerald-200 bg-emerald-50 text-emerald-900',
            'M5 10.5 8.5 14l6.5-7',
            'Examen recibido',
            'Criocord ya tiene tu resultado. Tu asesor(a) te contactará si hace falta algo más.',
        ],
        'tomada' => [
            'border-criocord-200 bg-criocord-50 text-criocord-900',
            'M10 5.5V10l3 2',
            'Prueba Toma',
            'Estamos esperando que el laboratorio nos envíe tu resultado.',
        ],
        'pendiente' => [
            'border-amber-200 bg-amber-50 text-amber-900',
            'M10 5.5V11m0 3.4h.01',
            'Falta tomarte la prueba',
            'Acércate con tu DNI a cualquiera de las sedes. No tiene costo: está incluido en tu contrato.',
        ],
        // Sin fila en la tabla de tomas no sabemos nada, y decirle que vaya al
        // laboratorio sería mandarla por segunda vez. Mejor que nos escriba.
        default => [
            'border-slate-200 bg-slate-50 text-slate-700',
            'M10 5.5V11m0 3.4h.01',
            'Estamos actualizando tus datos',
            'Tu contrato todavía no está en el nuevo registro de pruebas. Escríbenos por WhatsApp y te decimos en qué va.',
        ],
    };

    // La lista de sedes solo tiene sentido si todavía le falta la prueba.
    // De paso decide el ancho de la rejilla: sin ella sobraría una columna.
    $muestraSedes = $estado === 'pendiente' && $sedes->isNotEmpty();

    // Línea de tiempo: [título, fecha, ¿ya pasó?]
    $pasos = [
        ['Contrato firmado', $contrato->fecha_contrato, true],
        ['Prueba Toma', $fechaToma, $fechaToma !== null],
        ['Examen recibido', $fechaResultado, $fechaResultado !== null],
    ];
@endphp

<div class="animate-aparecer">

    <header class="mb-5 flex flex-wrap items-end justify-between gap-x-4 gap-y-2 border-b border-slate-200 pb-4 sm:mb-6">
        <div class="min-w-0">
            <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                Hola, {{ $contrato->primer_nombre }}
            </h2>
            <p class="mt-0.5 truncate text-sm text-slate-500">{{ $contrato->nombre_completo }}</p>
        </div>

        <p class="text-xs text-slate-400">
            Contrato
            <span class="ml-1 font-mono text-sm font-semibold text-slate-600">{{ $contrato->numero_contrato }}</span>
        </p>
    </header>

    {{--
        En celular todo va apilado. Desde md se parte en dos columnas y desde lg
        en tres, para usar el ancho de la pantalla en PC.
    --}}
    <div @class([
        'grid items-start gap-4 sm:gap-5 md:grid-cols-2',
        'lg:grid-cols-3' => $muestraSedes,
        // Sin sedes son dos tarjetas: el QR se queda en su ancho y el estado
        // ocupa el resto, en vez de dejar un tercio de pantalla vacío.
        'lg:grid-cols-[22rem_minmax(0,1fr)]' => ! $muestraSedes,
    ])>

        {{-- 1. El código que se escanea en recepción. --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm sm:p-6">
            <h3 class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Tu código</h3>

            {{-- El QR lo dibuja resources/js/qr.js leyendo data-qr.
                 Mientras tanto (o si falla) se ve el número en texto. --}}
            <div class="mx-auto mt-4 aspect-square w-full max-w-52 rounded-xl bg-white p-2 ring-1 ring-slate-200"
                 role="img"
                 aria-label="Código QR del contrato {{ $contrato->numero_contrato }}"
                 data-qr="{{ $contrato->numero_contrato }}">
                <div class="grid h-full place-items-center p-2 font-mono text-xs break-all text-slate-900">
                    {{ $contrato->numero_contrato }}
                </div>
            </div>

            <p class="mt-4 font-mono text-sm font-semibold tracking-wider break-all text-slate-900 sm:text-base">
                {{ $contrato->numero_contrato }}
            </p>

            <p class="mt-3 text-xs text-pretty text-slate-500">
                Muéstralo en recepción junto con tu DNI.
            </p>
        </section>

        {{-- 2. En qué va la prueba. --}}
        <div class="grid gap-4 sm:gap-5">
            <section role="status" class="flex items-start gap-3 rounded-2xl border p-5 shadow-sm sm:p-6 {{ $tono }}">
                <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="10" r="8.25" stroke="currentColor" stroke-width="1.5" opacity=".35" />
                    <path d="{{ $icono }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>

                <div class="min-w-0">
                    <p class="font-semibold">{{ $titulo }}</p>
                    <p class="mt-1 text-sm text-pretty opacity-90">{{ $mensaje }}</p>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-xs font-semibold tracking-wider text-slate-400 uppercase">Tu avance</h3>

                <ol class="mt-3 divide-y divide-slate-100">
                    @foreach ($pasos as [$paso, $fecha, $hecho])
                        <li class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                            <span @class([
                                'grid size-5 shrink-0 place-items-center rounded-full border-2',
                                'border-criocord-600 bg-criocord-600 text-white' => $hecho,
                                'border-slate-200 bg-slate-50' => ! $hecho,
                            ])>
                                @if ($hecho)
                                    <svg class="size-3" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                                        <path d="M2.5 6.2 4.8 8.5 9.5 3.8" stroke="currentColor" stroke-width="2"
                                              stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                @endif
                            </span>

                            <span @class([
                                'min-w-0 flex-1 text-sm',
                                'font-medium text-slate-800' => $hecho,
                                'text-slate-400' => ! $hecho,
                            ])>{{ $paso }}</span>

                            <span class="shrink-0 text-xs text-slate-400 tabular-nums">
                                {{ $fecha?->format('d/m/Y') ?: 'Pendiente' }}
                            </span>
                        </li>
                    @endforeach
                </ol>

                <dl class="mt-5 grid gap-3 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs tracking-wide text-slate-400 uppercase">Fecha de parto</dt>
                        <dd class="mt-0.5 font-medium text-slate-700">{{ $contrato->fecha_nacimiento?->format('d/m/Y') ?: 'Sin registrar' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs tracking-wide text-slate-400 uppercase">Sede de la toma</dt>
                        <dd class="mt-0.5 font-medium text-slate-700">{{ $toma?->dondeSeTomo() ?: 'Sin registrar' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        {{-- 3. Dónde puede ir: sedes agrupadas por alias.
               Solo si todavía le falta la prueba. Si ya se la tomó, o si no
               tenemos su toma registrada, mandarla al laboratorio sería
               mandarla dos veces. --}}
        @if ($muestraSedes)
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 md:col-span-2 lg:col-span-1">
                <h3 class="text-xs font-semibold tracking-wider text-slate-400 uppercase">
                    Sedes disponibles
                </h3>

                <div class="mt-4 space-y-4">
                    @foreach ($sedes->groupBy('alias') as $alias => $sedesAlias)
                        <div>
                            <h4 class="mb-2 text-sm font-semibold text-slate-800">
                                {{ $alias }}
                            </h4>

                            <ul class="flex flex-wrap gap-2">
                                @foreach ($sedesAlias as $sede)
                                    <li class="rounded-lg bg-criocord-50 px-3 py-1.5 text-sm font-medium text-criocord-800 ring-1 ring-criocord-100 ring-inset">
                                        {{ $sede->descripcion_sede }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-pretty text-slate-500">
                    Ve a la que te quede más cerca con tu DNI. No necesitas cita.
                </p>
            </section>
        @endif
    </div>
</div>
