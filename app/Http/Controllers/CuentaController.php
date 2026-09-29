<?php

namespace App\Http\Controllers;

use App\Enums\TipoCuenta;
use App\Http\Requests\StoreCuentaRequest;
use App\Http\Requests\UpdateCuentaRequest;
use App\Models\Cuenta;
use App\Models\Presupuesto;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CuentaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response|RedirectResponse
    {
        $activo = $this->presupuestoActivoOrRedirect();
        if ($activo instanceof RedirectResponse) {
            return $activo;
        }

        return Inertia::render('Cuentas/Index', [
            'cuentas' => Cuenta::where('presupuesto_id', $activo->id)
                ->with('moneda')
                ->orderBy('tipo')
                ->orderBy('nombre')
                ->get(),
            'presupuestoActivo' => $activo,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response|RedirectResponse
    {
        $activo = $this->presupuestoActivoOrRedirect();
        if ($activo instanceof RedirectResponse) {
            return $activo;
        }

        return Inertia::render('Cuentas/Create', [
            'presupuestoActivo' => $activo,
            'tiposCuenta' => TipoCuenta::opciones(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCuentaRequest $request): RedirectResponse
    {
        $activo = $this->presupuestoActivoOrRedirect();
        if ($activo instanceof RedirectResponse) {
            return $activo;
        }

        Cuenta::create($request->validated());

        return redirect()->route('cuentas.index')
            ->with('flash.success', 'Cuenta creada.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cuenta $cuenta): Response
    {
        $this->authorize('update', $cuenta);

        return Inertia::render('Cuentas/Edit', [
            'cuenta' => $cuenta->load('moneda'),
            'tiposCuenta' => TipoCuenta::opciones(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * moneda_codigo/presupuesto_id son fijos tras crear (design.md Decisión
     * 2 y 10). UpdateCuentaRequest ni siquiera los valida, pero esto es
     * defensa en profundidad por si llegaran en el payload raw.
     */
    public function update(UpdateCuentaRequest $request, Cuenta $cuenta): RedirectResponse
    {
        $this->authorize('update', $cuenta);

        $validated = array_diff_key($request->validated(), [
            'moneda_codigo' => 0,
            'presupuesto_id' => 0,
        ]);

        $cuenta->update($validated);

        return redirect()->route('cuentas.index')
            ->with('flash.success', 'Cuenta actualizada.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cuenta $cuenta): RedirectResponse
    {
        $this->authorize('delete', $cuenta);

        $cuenta->delete();

        return redirect()->route('cuentas.index')
            ->with('flash.info', 'Cuenta eliminada.');
    }

    /**
     * Presupuesto activo del usuario autenticado, o un redirect a /dashboard
     * con flash de aviso si no tiene ninguno. Centraliza el guard que pide
     * el Grupo 5 en un solo lugar en vez de repetir el mensaje en 4 métodos.
     */
    private function presupuestoActivoOrRedirect(): Presupuesto|RedirectResponse
    {
        $activo = auth()->user()->presupuestoActivo;

        return $activo ?? redirect()->route('dashboard')
            ->with('flash.danger', 'Necesitas un presupuesto activo para gestionar cuentas.');
    }
}
