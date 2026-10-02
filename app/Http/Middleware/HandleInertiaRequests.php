<?php

namespace App\Http\Middleware;

use App\Models\Moneda;
use App\Models\Transaccion;
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
        $request->user()?->loadMissing(['presupuestos', 'presupuestoActivo.monedaBase']);

        return [
            ...parent::share($request),
            'iconosPresupuesto' => config('mynab.iconos_presupuesto'),
            'iconosGrupoCategoria' => config('mynab.iconos_grupo_categoria'),
            'monedasActivas' => Moneda::activas()->orderBy('codigo')->get(),
            // Closure: Inertia la resuelve en el mismo momento que cualquier
            // otro prop de un full load (ver Response::resolvePartialProperties
            // — solo LazyProp/Deferrable se saltan ahí, un Closure plano no).
            // No es "solo se ejecuta si la página la pide"; se ejecuta en
            // cada visita autenticada, igual que monedasActivas. Se usa como
            // closure solo por conveniencia sintáctica del guard null.
            'cuentasDelPresupuestoActivo' => function () use ($request) {
                $user = $request->user();

                if (! $user || ! $user->presupuesto_activo_id) {
                    return [];
                }

                $user->loadMissing('presupuestoActivo.cuentas.moneda');

                return $user->presupuestoActivo?->cuentas ?? [];
            },
            'gruposCategoriasDelPresupuestoActivo' => function () use ($request) {
                $user = $request->user();

                if (! $user || ! $user->presupuesto_activo_id) {
                    return [];
                }

                $user->loadMissing('presupuestoActivo.gruposCategorias.categorias');

                return $user->presupuestoActivo?->gruposCategorias ?? [];
            },
            'beneficiariosDelPresupuestoActivo' => function () use ($request) {
                $user = $request->user();

                if (! $user || ! $user->presupuesto_activo_id) {
                    return [];
                }

                $user->loadMissing('presupuestoActivo.beneficiarios');

                return $user->presupuestoActivo?->beneficiarios ?? [];
            },
            // Desviación deliberada del patrón loadMissing de los closures
            // anteriores: Presupuesto::transacciones() es un hasManyThrough,
            // y loadMissing() traería TODAS las transacciones del presupuesto
            // a memoria antes de poder aplicar un limit() en PHP — con
            // potencialmente cientos de filas por mes, es exactamente el tipo
            // de query que este prop necesita evitar. Una query directa con
            // `limit(20)` en SQL es la única forma correcta de limitar en la
            // base de datos, no en PHP tras cargar de más.
            'transaccionesRecientesDelPresupuestoActivo' => function () use ($request) {
                $user = $request->user();

                if (! $user || ! $user->presupuesto_activo_id) {
                    return [];
                }

                return Transaccion::whereHas('cuenta', fn ($q) => $q->where('presupuesto_id', $user->presupuesto_activo_id))
                    ->with([
                        'cuenta:id,nombre,moneda_codigo',
                        'categoria:id,nombre',
                        'beneficiario:id,nombre',
                    ])
                    ->orderByDesc('fecha_hora')
                    ->limit(20)
                    ->get();
            },
        ];
    }
}
