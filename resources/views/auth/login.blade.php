<!DOCTYPE html>
<html lang="es" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">
    <main class="flex min-h-full items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <div class="flex flex-col gap-4 justify-center items-center">
                    <img class="w-full max-w-[150px]" src="{{ asset('images/logos/criocord_logo.png') }}" alt="Logo CrioCord" />
                    <img class="w-full max-w-[150px]" src="{{ asset('images/logos/lazo_de_vida_logo.png') }}" alt="Logo Lazo de Vida" />
                </div>

                <h1 class="sr-only">{{ config('app.name') }}</h1>
                <p class="text-sm text-slate-500 max-w-[250px] mx-auto mt-5">Ingresa tu correo y contraseña para acceder al panel administrativo</p>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
                <form 
                    method="POST" 
                    action="{{ route('login.store') }}" 
                    class="space-y-5"
                    onsubmit="this.querySelector('button[type=submit]').disabled = true"
                >
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            value="{{ old('email') }}"
                            autocomplete="username" 
                            autofocus 
                            required
                            @class([
                                'mt-1.5 block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 shadow-xs focus:outline-none focus:ring-2',
                                'border-slate-300 focus:border-sky-600 focus:ring-sky-600/20' => ! $errors->has('email'),
                                'border-red-400 focus:border-red-500 focus:ring-red-500/20' => $errors->has('email'),
                            ])
                        />
                        @error('email')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">Contraseña</label>
                        <input 
                            id="password" 
                            name="password" 
                            type="password"
                            autocomplete="current-password" 
                            required
                            class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-xs focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20"
                        />
                    </div>

                    <div>
                        <label for="empresa" class="block text-sm font-medium text-slate-700">Compañía</label>
                        <select 
                            id="empresa" 
                            name="empresa" 
                            required
                            class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-xs focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/20"
                        >
                            <option value="" disabled @selected(! old('empresa'))>Selecciona una compañía</option>
                            @foreach ($empresas as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected(old('empresa') == $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button 
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-sky-800 disabled:opacity-60 cursor-pointer"
                    >
                        Ingresar
                    </button>
                </form>
            </div>

            <div class="mt-6 text-center text-sm text-slate-500">¿Olvidaste tu contraseña?</div>
        </div>
    </main>
</body>

</html>
