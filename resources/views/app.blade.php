<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @routes
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/dashboard.tsx'])

    <x-inertia::head />
</head>

<body>
    <x-inertia::app />
</body>

</html>
