<!DOCTYPE html>
<html lang="es" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Consulta tu prueba serológica · Criocord</title>

    @vite(['resources/css/app.css', 'resources/js/clientes.js'])
</head>

<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased">

    {{--
        Barra superior. El logo vive en public/img: cambia la ruta del asset()
        si quieres usar otro archivo.
    --}}
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">

            <a href="{{ route('clientes.index') }}" class="shrink-0" aria-label="Criocord, ir al inicio">
                <img src="{{ asset('img/criocord_logo.png') }}" alt="Criocord" class="h-6 w-auto max-w-[110px] sm:h-9 sm:max-w-none">
            </a>

            {{--
                Sesión: solo se ve cuando ya hay una consulta. En el POST normal
                lo decide isset($contrato); con fetch lo hace clientes.js.
            --}}
            <div data-sesion @class(['flex shrink-0 items-center gap-1.5 sm:gap-3', 'hidden' => ! isset($contrato)])>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-criocord-50 px-2.5 py-1.5 text-xs sm:px-3 font-medium text-criocord-800 ring-1 ring-criocord-100 sm:text-sm">
                    <span class="hidden text-criocord-600 sm:inline">Buscado por DNI</span>
                    <span class="text-criocord-600 sm:hidden">DNI</span>
                    <span data-sesion-dni class="font-mono font-semibold tracking-wider tabular-nums">{{ $contrato->identificacion1 ?? '' }}</span>
                </span>

                <a href="{{ route('clientes.index') }}" data-salir
                   class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1.5 text-xs sm:px-3 font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:ring-3 focus:ring-slate-200 focus:outline-none sm:text-sm">
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M12.5 14.5v1.5a1.5 1.5 0 0 1-1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5V4A1.5 1.5 0 0 1 5 2.5h6a1.5 1.5 0 0 1 1.5 1.5v1.5M9 10h8m0 0-2.5-2.5M17 10l-2.5 2.5"
                              stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Salir
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">

        {{-- Buscador. Se oculta en cuanto hay resultado. --}}
        <section data-buscador @class(['mx-auto max-w-xl', 'hidden' => isset($contrato)])>

            <div class="text-center">
                <h1 class="text-2xl font-bold tracking-tight text-balance text-slate-900 sm:text-3xl">
                    Consulta tu prueba serológica
                </h1>
                <p class="mx-auto mt-2 max-w-sm text-sm text-pretty text-slate-500">
                    Escribe tu DNI y te decimos en qué va tu prueba.
                </p>
            </div>

            <form method="POST" action="{{ route('clientes.consultar') }}" data-form-dni
                  class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:mt-8 sm:p-6">
                @csrf

                <label for="dni" class="block text-sm font-semibold text-slate-700">Tu DNI</label>

                {{-- En celular el botón va debajo; desde sm, al costado. --}}
                <div class="mt-2 sm:flex sm:gap-3">
                    <input id="dni" name="dni" value="{{ old('dni') }}"
                           maxlength="8" inputmode="numeric" autocomplete="off" placeholder="8 dígitos"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-center font-mono text-lg tracking-[0.3em] tabular-nums placeholder:tracking-normal placeholder:font-sans focus:border-criocord-500 focus:ring-3 focus:ring-criocord-100 focus:outline-none sm:flex-1 sm:text-left">

                    <button type="submit"
                            class="mt-3 w-full rounded-xl bg-criocord-600 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-criocord-700 focus:ring-3 focus:ring-criocord-200 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60 sm:mt-0 sm:w-auto sm:shrink-0">
                        Consultar
                    </button>
                </div>

                {{-- Errores: del POST normal y, por JS, los que devuelve el fetch. --}}
                <p data-error-dni class="mt-3 flex items-start gap-1.5 text-sm font-medium text-red-600 empty:hidden">@error('dni'){{ $message }}@enderror</p>
            </form>

            <p class="mt-4 text-center text-xs text-slate-400">
                La prueba está incluida en tu contrato Criocord.
            </p>
        </section>

        <div data-resultado>
            @isset($contrato)
                @include('serologia.clientes.partials.ficha')
            @endisset
        </div>
    </main>
</body>

</html>
