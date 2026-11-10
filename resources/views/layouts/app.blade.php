<!DOCTYPE html>
<html lang="es" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Inicio') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>

<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-slate-900">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-sky-700 text-sm text-white">
                        {{ Str::upper(Str::substr(config('app.name'), 0, 1)) }}
                    </span>
                    <span class="hidden sm:inline">{{ config('app.name') }}</span>
                </a>

                <nav class="flex items-center gap-1 text-sm">
                    @php
                        $enlaces = [
                            'home' => 'Inicio',
                        ];
                    @endphp

                    @foreach ($enlaces as $ruta => $etiqueta)
                        <a href="{{ route($ruta) }}"
                           @class([
                               'rounded-lg px-3 py-2 font-medium transition',
                               'bg-sky-50 text-sky-800' => request()->routeIs($ruta),
                               'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => ! request()->routeIs($ruta),
                           ])>{{ $etiqueta }}</a>
                    @endforeach
                </nav>
            </div>

            <div class="flex items-center gap-3 sm:gap-5">
                <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-medium text-sky-800 ring-1 ring-sky-200">
                    {{ session('empresa_nombre') }}
                </span>

                <div class="hidden text-right text-sm leading-tight sm:block">
                    <div class="font-medium text-slate-900">{{ auth()->user()->name }}</div>
                    <div class="text-slate-500">{{ auth()->user()->codigo }}</div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        @yield('contenido')
    </main>
</body>

</html>
