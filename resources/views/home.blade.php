@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <h2 class="text-2xl font-semibold text-slate-900">
        Bienvenido, {{ auth()->user()->nombres ?: auth()->user()->name }}
    </h2>
    <p class="mt-1 text-slate-500">Estás trabajando con {{ session('empresa_nombre') }}.</p>

    <div
        data-pagina="dashboard"
        data-props="{{ json_encode(['usuario' => auth()->user()->name]) }}"
    ></div>
@endsection
