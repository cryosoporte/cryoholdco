<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'assetUrl' => rtrim(asset(''), '/'),
            'empresa' => $request->user()?->empresa,
            'user' => $request->user()?->only(['name', 'email']),
            'roles' => fn (): array => $request->user() instanceof User
                ? $request->user()->roles->pluck('rol.value')->all()
                : [],
            'rutasReportes' => function () use ($request): array {
                $usuario = $request->user();
                if (! $usuario instanceof User) {
                    return [];
                }

                return $usuario->can('comisiones.ver')
                    ? ['reportes.comisiones', 'reportes.fuentes']
                    : ['reportes.fuentes'];
            },
        ];
    }
}
