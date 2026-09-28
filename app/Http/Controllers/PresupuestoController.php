<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePresupuestoRequest;
use App\Http\Requests\UpdatePresupuestoRequest;
use App\Models\Presupuesto;
use Inertia\Inertia;
use Inertia\Response;

class PresupuestoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('Presupuestos/Index', [
            'presupuestos' => auth()->user()->presupuestos()->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Presupuestos/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePresupuestoRequest $request)
    {
        $presupuesto = auth()->user()->presupuestos()->create($request->validated());

        if (auth()->user()->presupuesto_activo_id === null) {
            auth()->user()->update(['presupuesto_activo_id' => $presupuesto->id]);
        }

        return redirect()->route('presupuestos.index')
            ->with('flash.success', 'Presupuesto creado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Presupuesto $presupuesto): Response
    {
        $this->authorize('view', $presupuesto);

        return Inertia::render('Presupuestos/Show', [
            'presupuesto' => $presupuesto,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Presupuesto $presupuesto): Response
    {
        $this->authorize('update', $presupuesto);

        return Inertia::render('Presupuestos/Edit', [
            'presupuesto' => $presupuesto,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePresupuestoRequest $request, Presupuesto $presupuesto)
    {
        $this->authorize('update', $presupuesto);

        $presupuesto->update($request->validated());

        return redirect()->route('presupuestos.index')
            ->with('flash.success', 'Presupuesto actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Presupuesto $presupuesto)
    {
        $this->authorize('delete', $presupuesto);

        $user = auth()->user();
        $eraActivo = $user->presupuesto_activo_id === $presupuesto->id;

        $presupuesto->delete();

        if ($eraActivo) {
            // Criterio: más antiguo restante para reasignación determinística
            // (ni el spec ni el design dicen cuál "siguiente" elegir).
            $siguiente = $user->presupuestos()->oldest()->first();
            $user->update(['presupuesto_activo_id' => $siguiente?->id]);
        }

        if ($user->fresh()->presupuesto_activo_id === null) {
            return redirect()->route('dashboard')
                ->with('flash.info', 'Crea un nuevo presupuesto para continuar.');
        }

        return redirect()->route('presupuestos.index')
            ->with('flash.success', 'Presupuesto eliminado.');
    }

    /**
     * Marcar el presupuesto dado como el activo del usuario autenticado.
     */
    public function seleccionar(Presupuesto $presupuesto)
    {
        $this->authorize('view', $presupuesto);

        auth()->user()->update(['presupuesto_activo_id' => $presupuesto->id]);

        return redirect()->back()
            ->with('flash.success', "Presupuesto activo: {$presupuesto->nombre}");
    }
}
