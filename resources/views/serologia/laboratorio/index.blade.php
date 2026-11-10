<!DOCTYPE html>
<html lang="es" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Mostrador de serología · Criocord</title>

    @vite(['resources/css/app.css', 'resources/js/laboratorio.js'])
</head>

<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased">

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">

            <div class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('img/criocord_logo.png') }}" alt="Criocord"
                     class="h-6 w-auto max-w-[110px] shrink-0 sm:h-9 sm:max-w-none">
                <span class="hidden text-xs font-semibold tracking-wider text-slate-400 uppercase sm:inline">
                    Mostrador
                </span>
            </div>

            {{--
                Laboratorio y sede. Cuando exista el inicio de sesión por
                empresa, los dos salen del usuario y este bloque se borra.
            --}}
            <form method="POST" action="{{ route('laboratorio.sede') }}" data-form-sede
                  class="flex flex-wrap items-center gap-2">
                @csrf

                <label for="laboratorio" class="sr-only">Laboratorio</label>
                <select id="laboratorio" name="laboratorio"
                        class="rounded-lg border border-slate-300 bg-white py-1.5 pr-8 pl-3 text-sm font-medium text-slate-700 focus:border-criocord-500 focus:ring-3 focus:ring-criocord-100 focus:outline-none">
                    @foreach ($laboratorios as $lab)
                        <option value="{{ $lab->identificacion }}" @selected($lab->identificacion === $laboratorio)>
                            {{ $lab->alias }}
                        </option>
                    @endforeach
                </select>

                <label for="sede" class="sr-only">Sede</label>
                <select id="sede" name="sede"
                        class="rounded-lg border border-slate-300 bg-white py-1.5 pr-8 pl-3 text-sm font-medium text-slate-700 focus:border-criocord-500 focus:ring-3 focus:ring-criocord-100 focus:outline-none">
                    @foreach ($sedes as $s)
                        <option value="{{ $s->codigo_sede }}" @selected($s->codigo_sede === $sede)>
                            {{ $s->descripcion_sede }}
                        </option>
                    @endforeach
                </select>

                {{-- Sin JavaScript hace falta un botón; con JavaScript el
                     cambio del select ya dispara el envío. --}}
                <noscript>
                    <button type="submit"
                            class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-semibold text-white">
                        Cambiar
                    </button>
                </noscript>
            </form>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">

        <div class="grid items-start gap-5 lg:grid-cols-3">

            {{-- Escáner y resultado. --}}
            <section class="space-y-5 lg:col-span-2">

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        Clienta Criocord
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Escanea su QR o escribe el código del contrato o su DNI.
                    </p>

                    {{-- La cámara. El botón solo aparece si el navegador la tiene;
                         laboratorio.js le quita el "hidden". --}}
                    <button type="button" data-abrir-camara hidden
                            class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-criocord-600 px-6 py-4 text-base font-semibold text-white shadow-sm transition hover:bg-criocord-700 focus:ring-3 focus:ring-criocord-200 focus:outline-none">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3" />
                            <path d="M7 12h10" />
                        </svg>
                        Escanear QR con la cámara
                    </button>

                    <div data-camara hidden class="mt-5">
                        <div class="relative overflow-hidden rounded-xl bg-slate-900">
                            <video data-video playsinline muted class="block max-h-80 w-full object-cover"></video>
                            <div class="pointer-events-none absolute inset-6 rounded-lg border-2 border-white/70"></div>
                        </div>

                        <p data-camara-estado role="status"
                           class="mt-3 text-center text-sm font-medium text-slate-500">
                            Apunta al código QR de la clienta.
                        </p>

                        <button type="button" data-cerrar-camara
                                class="mt-3 w-full rounded-xl border border-slate-300 bg-white px-6 py-3 font-semibold text-slate-700 transition hover:bg-slate-50 focus:ring-3 focus:ring-slate-200 focus:outline-none">
                            Cerrar cámara
                        </button>
                    </div>

                    <div data-separador class="my-5 flex items-center gap-3 text-xs text-slate-400">
                        <span class="h-px flex-1 bg-slate-200"></span>
                        o escribe el código o el DNI
                        <span class="h-px flex-1 bg-slate-200"></span>
                    </div>

                    <form method="POST" action="{{ route('laboratorio.consultar') }}" data-form-codigo>
                        @csrf

                        <label for="codigo" class="block text-sm font-semibold text-slate-700">
                            Código o DNI
                        </label>

                        <div class="mt-2 sm:flex sm:gap-3">
                            <input id="codigo" name="codigo" value="{{ old('codigo') }}"
                                   autocomplete="off" autocapitalize="characters" spellcheck="false"
                                   placeholder="PE-CR-2026-000000-0 o DNI"
                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-base uppercase placeholder:normal-case placeholder:font-sans focus:border-criocord-500 focus:ring-3 focus:ring-criocord-100 focus:outline-none sm:flex-1">

                            <button type="submit"
                                    class="mt-3 w-full rounded-xl border border-slate-300 bg-white px-6 py-3 font-semibold text-slate-700 transition hover:bg-slate-50 focus:ring-3 focus:ring-slate-200 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 sm:mt-0 sm:w-auto sm:shrink-0">
                                Buscar
                            </button>
                        </div>

                        <p data-error-codigo class="mt-3 text-sm font-medium text-red-600 empty:hidden">@error('codigo'){{ $message }}@enderror</p>
                    </form>

                    <p class="mt-4 text-xs text-pretty text-slate-400">
                        Con un lector QR de mostrador (USB) el código se escribe solo
                        en este campo. Si la clienta no trae el celular, digita su DNI.
                    </p>
                </div>

                <div data-semaforo>
                    @include('serologia.laboratorio.partials.semaforo')
                </div>
            </section>

            {{-- Lo tomado hoy en esta sede. --}}
            <section data-hoy class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                @include('serologia.laboratorio.partials.hoy')
            </section>
        </div>
    </main>
</body>

</html>
