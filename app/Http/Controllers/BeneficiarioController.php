<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBeneficiarioRequest;
use App\Http\Requests\UpdateBeneficiarioRequest;
use App\Models\Beneficiario;
use App\Models\Presupuesto;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BeneficiarioController extends Controller
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

        return Inertia::render('Beneficiarios/Index', [
            'beneficiarios' => Beneficiario::where('presupuesto_id', $activo->id)
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

        return Inertia::render('Beneficiarios/Create', [
            'presupuestoActivo' => $activo,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBeneficiarioRequest $request): RedirectResponse
    {
        $activo = $this->presupuestoActivoOrRedirect();
        if ($activo instanceof RedirectResponse) {
            return $activo;
        }

        Beneficiario::create($request->validated());

        return redirect()->route('beneficiarios.index')
            ->with('flash.success', 'Beneficiario creado.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Beneficiario $beneficiario): Response
    {
        $this->authorize('update', $beneficiario);

        return Inertia::render('Beneficiarios/Edit', [
            'beneficiario' => $beneficiario,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * presupuesto_id es fijo tras crear (design.md Decisión 5).
     * UpdateBeneficiarioRequest ni siquiera lo valida, pero esto es defensa
     * en profundidad por si llegara en el payload raw.
     */
    public function update(UpdateBeneficiarioRequest $request, Beneficiario $beneficiario): RedirectResponse
    {
        $this->authorize('update', $beneficiario);

        $validated = array_diff_key($request->validated(), [
            'presupuesto_id' => 0,
        ]);

        $beneficiario->update($validated);

        return redirect()->route('beneficiarios.index')
            ->with('flash.success', 'Beneficiario actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Beneficiario $beneficiario): RedirectResponse
    {
        $this->authorize('delete', $beneficiario);

        $beneficiario->delete();

        return redirect()->route('beneficiarios.index')
            ->with('flash.info', 'Beneficiario eliminado.');
    }

    /**
     * Presupuesto activo del usuario autenticado, o un redirect a /dashboard
     * con flash de aviso si no tiene ninguno. Centraliza el guard en un solo
     * lugar en vez de repetir el mensaje en 3 métodos (mismo patrón que
     * CuentaController y GrupoCategoriaController).
     */
    private function presupuestoActivoOrRedirect(): Presupuesto|RedirectResponse
    {
        $activo = auth()->user()->presupuestoActivo;

        return $activo ?? redirect()->route('dashboard')
            ->with('flash.danger', 'Necesitas un presupuesto activo para gestionar beneficiarios.');
    }
}
