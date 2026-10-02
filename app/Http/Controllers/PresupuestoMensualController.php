<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Services\PresupuestoMensualService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PresupuestoMensualController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $activo = $this->presupuestoActivoOrRedirect();
        if ($activo instanceof RedirectResponse) {
            return $activo;
        }

        $año = $request->integer('año', now()->year);
        $mes = $request->integer('mes', now()->month);

        if ($año < 2020 || $año > 2100 || $mes < 1 || $mes > 12) {
            return redirect()->route('presupuesto-mensual.index')
                ->with('flash.danger', 'El mes solicitado no es válido.');
        }

        $vista = app(PresupuestoMensualService::class)->obtenerVistaMensual($activo, $año, $mes);

        return Inertia::render('PresupuestoMensual/Index', [
            'vista' => $vista,
            'año' => $año,
            'mes' => $mes,
        ]);
    }

    /**
     * Presupuesto activo del usuario autenticado, o un redirect a /dashboard
     * con flash de aviso si no tiene ninguno. Mismo patrón que
     * Cuenta/GrupoCategoria/Beneficiario/TransaccionController.
     */
    private function presupuestoActivoOrRedirect(): Presupuesto|RedirectResponse
    {
        $activo = auth()->user()->presupuestoActivo;

        return $activo ?? redirect()->route('dashboard')
            ->with('flash.danger', 'Necesitas un presupuesto activo para ver el presupuesto mensual.');
    }
}
