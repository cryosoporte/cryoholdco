<!DOCTYPE html>
<html lang="es" data-theme="criocord">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/inertia.tsx'])

    <x-inertia::head />
</head>

<body>
    <x-inertia::app />
</body>

</html>
