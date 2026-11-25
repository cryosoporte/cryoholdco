<?php

namespace App\Administracion;

class CatalogoPermisos
{
    /** @var array<string, array{modulo: string, nombre: string, descripcion: string}> */
    public const PERMISOS = [
        'administracion.laboratorios' => [
            'modulo' => 'Administración',
            'nombre' => 'Gestionar accesos de laboratorios',
            'descripcion' => 'Permite consultar, crear y editar los accesos de los laboratorios.',
        ],
        'administracion.usuarios' => [
            'modulo' => 'Administración',
            'nombre' => 'Asignar roles a usuarios',
            'descripcion' => 'Permite consultar las personas y cambiar los roles de sus cuentas.',
        ],
        'administracion.roles' => [
            'modulo' => 'Administración',
            'nombre' => 'Gestionar roles y permisos',
            'descripcion' => 'Permite crear, editar y eliminar roles y elegir sus permisos.',
        ],
        'comisiones.ver' => [
            'modulo' => 'Comisiones',
            'nombre' => 'Ver el reporte de comisiones',
            'descripcion' => 'Permite consultar el reporte, sus liquidaciones y las vistas previas de correo.',
        ],
        'comisiones.procesar' => [
            'modulo' => 'Comisiones',
            'nombre' => 'Procesar liquidaciones',
            'descripcion' => 'Permite procesar una liquidación y enviarla a revisión.',
        ],
        'comisiones.aprobar' => [
            'modulo' => 'Comisiones',
            'nombre' => 'Aprobar liquidaciones',
            'descripcion' => 'Permite aprobar una liquidación procesada por otra persona.',
        ],
        'comisiones.rechazar' => [
            'modulo' => 'Comisiones',
            'nombre' => 'Rechazar liquidaciones',
            'descripcion' => 'Permite devolver una liquidación procesada por otra persona con un comentario.',
        ],
        'comisiones.enviar' => [
            'modulo' => 'Comisiones',
            'nombre' => 'Enviar y reenviar al personal',
            'descripcion' => 'Permite enviar por correo las comisiones aprobadas y reintentar los envíos fallidos.',
        ],
    ];

    /**
     * Permisos que solo puede tener el rol ADMINISTRADOR. Quien gestiona usuarios o roles
     * puede repartir cualquier acceso, así que fuera de ese rol equivaldrían a serlo.
     *
     * @var list<string>
     */
    public const EXCLUSIVOS_ADMINISTRADOR = ['administracion.usuarios', 'administracion.roles'];

    /** @return list<string> */
    public static function codigosAdministracion(): array
    {
        return array_values(array_filter(
            array_keys(self::PERMISOS),
            fn (string $codigo): bool => str_starts_with($codigo, 'administracion.'),
        ));
    }
}
