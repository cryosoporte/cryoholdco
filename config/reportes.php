<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prefijo de las tablas de Reportes
    |--------------------------------------------------------------------------
    |
    | El entorno local usa la misma base Oracle que producción. Con el prefijo
    | "PRUEBA_" las cargas de prueba escriben en PRUEBA_FUENTES_DATOS y
    | PRUEBA_FUENTE_FACTURAS_ORACLE sin tocar las tablas reales. En producción
    | la variable queda vacía.
    |
    */

    'prefijo_tablas' => env('REPORTES_PREFIJO_TABLAS', ''),

    /*
    |--------------------------------------------------------------------------
    | Zona horaria
    |--------------------------------------------------------------------------
    |
    | La aplicación trabaja en UTC; la fecha de última actualización de una
    | fuente se guarda y se muestra en hora de Lima.
    |
    */

    'zona_horaria' => 'America/Lima',

    /*
    |--------------------------------------------------------------------------
    | Filas por bloque
    |--------------------------------------------------------------------------
    |
    | Cuántas filas se insertan en cada INSERT al cargar una fuente. Facturas
    | Oracle tiene 46 columnas, así que 100 filas son 4 600 parámetros por
    | sentencia. Se calibra midiendo con el histórico completo.
    |
    */

    'filas_por_bloque' => (int) env('REPORTES_FILAS_POR_BLOQUE', 100),
    'cargas_asincronas' => env('REPORTES_CARGAS_ASINCRONAS', false),

    /*
    |--------------------------------------------------------------------------
    | Comisiones
    |--------------------------------------------------------------------------
    |
    | "datos" elige qué muestra la pantalla: "reales" calcula con COM_META, la
    | fuente de facturas y USERS; "ejemplo" usa los datos fijos del prototipo.
    | En los dos casos el flujo de aprobación vive en la sesión y los correos
    | solo van a ejemplo.test por Mailtrap.
    |
    */

    'comisiones_demo' => [
        'datos' => env('REPORTES_COMISIONES_DATOS', 'reales'),
        'pausa_envio_ms' => (int) env('REPORTES_COMISIONES_PAUSA_ENVIO_MS', 1000),
    ],

];
