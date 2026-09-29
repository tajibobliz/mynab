<?php

namespace App\Http\Controllers;

use App\Models\Moneda;
use Inertia\Inertia;
use Inertia\Response;

class MonedaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Monedas/Index', [
            'monedas' => Moneda::orderBy('codigo')->get(),
        ]);
    }

    /**
     * Activa o desactiva una moneda. Sin Policy (catálogo global, cualquier
     * usuario autenticado puede togglear en esta fase — ver design.md).
     */
    public function toggle(Moneda $moneda)
    {
        if ($moneda->activa && $moneda->presupuestos()->exists()) {
            $enUso = $moneda->presupuestos()->pluck('nombre')->implode(', ');

            return back()->with('flash.danger', "No se puede desactivar {$moneda->codigo}: en uso por {$enUso}");
        }

        $moneda->update(['activa' => ! $moneda->activa]);

        return back()->with(
            'flash.success',
            $moneda->activa ? "{$moneda->codigo} activada" : "{$moneda->codigo} desactivada"
        );
    }
}
