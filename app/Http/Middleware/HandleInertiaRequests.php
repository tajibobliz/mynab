<?php

namespace App\Http\Middleware;

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
        // Carga las relaciones ANTES del array de retorno: Jetstream comparte
        // `auth.user` en su propio middleware vendor (ShareInertiaData) vía
        // $user->toArray(), que serializa cualquier relación ya cargada en la
        // instancia. Como ambos middlewares resuelven el mismo objeto de
        // Auth::user(), esto basta para exponer
        // `auth.user.presupuestos` / `.presupuestoActivo` sin tocar el vendor.
        $request->user()?->loadMissing(['presupuestos', 'presupuestoActivo']);

        return [
            ...parent::share($request),
            'iconosPresupuesto' => config('mynab.iconos_presupuesto'),
        ];
    }
}
