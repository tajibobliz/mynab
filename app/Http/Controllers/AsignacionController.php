<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAsignacionRequest;
use App\Models\Asignacion;
use Illuminate\Http\RedirectResponse;

class AsignacionController extends Controller
{
    /**
     * Upsert por (presupuesto_id, categoria_id, año, mes) — design.md
     * Decisión 6. Sin index/create/edit/update/destroy/show: la UI entera
     * (edición inline en PresupuestoMensual/Index.vue) vive sobre este único
     * endpoint.
     */
    public function store(StoreAsignacionRequest $request): RedirectResponse
    {
        $validado = $request->validated();

        Asignacion::updateOrCreate(
            [
                'presupuesto_id' => $validado['presupuesto_id'],
                'categoria_id' => $validado['categoria_id'],
                'año' => $validado['año'],
                'mes' => $validado['mes'],
            ],
            ['monto_centavos' => $validado['monto_centavos']],
        );

        return redirect()->back()->with('flash.success', 'Asignación guardada.');
    }
}
