<?php

namespace App\Reportes\Comisiones;

use App\Administracion\RolSistema;

class PermisosComisiones
{
    public const ROLES_POR_ACCION = [
        'ver' => [RolSistema::Administrador, RolSistema::Gerencia],
        'procesar' => [RolSistema::Administrador],
        'aprobar' => [RolSistema::Gerencia],
        'rechazar' => [RolSistema::Gerencia],
        'enviar' => [RolSistema::Administrador],
    ];
}
