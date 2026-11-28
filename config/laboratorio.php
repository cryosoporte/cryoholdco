<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carpeta raíz de documentos por empresa
    |--------------------------------------------------------------------------
    |
    | Laboratorio guarda los documentos de cada contrato en una carpeta cuyo
    | nombre empieza por el número de contrato base (por ejemplo
    | "15280-ST-MUÑOZ CASTRO"). La clave es el código de la empresa
    | (COR_COMPANIAS.CODIGO_COMPANIA): 001 Criocord y 002 Lazo de Vida.
    | La ruta debe ser visible dentro del contenedor y se monta como solo lectura.
    |
    */

    'raices' => [
        '001' => env('LABORATORIO_RUTA_CRIOCORD'),
        '002' => env('LABORATORIO_RUTA_LAZO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tamaño máximo de los adjuntos de un correo
    |--------------------------------------------------------------------------
    |
    | Suma de los archivos seleccionados, en megabytes. Los proveedores de
    | correo suelen rechazar mensajes de más de 20-25 MB.
    |
    */

    'maximo_adjuntos_mb' => (int) env('LABORATORIO_MAXIMO_ADJUNTOS_MB', 20),

    /*
    |--------------------------------------------------------------------------
    | Archivos que se envían por defecto en el envío masivo
    |--------------------------------------------------------------------------
    |
    | En el envío masivo solo se adjuntan los archivos de la carpeta del contrato
    | cuyo nombre contiene alguno de estos textos (sin distinguir mayúsculas).
    |
    */

    'archivos_masivo' => ['RHE', 'RMB', 'RSC', 'MSC', 'REPORTE', 'RPU'],

    /*
    |--------------------------------------------------------------------------
    | Registrar el envío en LAB_COLECTAS
    |--------------------------------------------------------------------------
    |
    | Tras enviar los documentos se marca FLAG_ENVIO_CORREO, ENVIO_FECHA_CORREO
    | y USUARIO_ENVIO. En local la aplicación usa el Oracle real: ponlo en
    | false (LABORATORIO_REGISTRAR_ENVIO=false) para probar sin marcar contratos.
    |
    */

    'registrar_envio' => (bool) env('LABORATORIO_REGISTRAR_ENVIO', true),

    /*
    |--------------------------------------------------------------------------
    | Correo de prueba
    |--------------------------------------------------------------------------
    |
    | Si se define (LABORATORIO_CORREO_PRUEBA=alguien@cryoholdco.com), todos los
    | correos de Informes se envían a esa dirección en lugar de a la del cliente.
    | Déjalo vacío en producción.
    |
    */

    'correo_prueba' => env('LABORATORIO_CORREO_PRUEBA'),

    /*
    |--------------------------------------------------------------------------
    | Marca del correo por empresa
    |--------------------------------------------------------------------------
    |
    | «fondo» es la imagen de fondo de los formatos de la empresa (hoja A4 con
    | encabezado y pie). Sin fondo, el correo usa un encabezado con el logo.
    |
    */

    'marcas' => [
        '001' => [
            'nombre' => 'CrioCord',
            'logo' => 'images/logos/criocord_logo.png',
            'web' => 'www.criocord.com.pe',
            'color' => '#1F3A5F',
            'fondo' => 'https://web.criocord.com.pe/formatosservicioalcliente/fondoformatos.jpg',
        ],
        '002' => [
            'nombre' => 'Lazo de Vida',
            'logo' => 'images/logos/lazo_de_vida_logo.png',
            'web' => null,
            'color' => '#1F3A5F',
            'fondo' => null,
        ],
    ],

];
