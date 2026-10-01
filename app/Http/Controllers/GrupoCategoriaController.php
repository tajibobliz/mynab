<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGrupoCategoriaRequest;
use App\Http\Requests\UpdateGrupoCategoriaRequest;
use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GrupoCategoriaController extends Controller
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

        return Inertia::render('GruposCategorias/Index', [
            'grupos' => GrupoCategoria::where('presupuesto_id', $activo->id)
                ->with(['categorias' => fn ($query) => $query->orderBy('nombre')])
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

        return Inertia::render('GruposCategorias/Create', [
            'presupuestoActivo' => $activo,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGrupoCategoriaRequest $request): RedirectResponse
    {
        $activo = $this->presupuestoActivoOrRedirect();
        if ($activo instanceof RedirectResponse) {
            return $activo;
        }

        GrupoCategoria::create($request->validated());

        return redirect()->route('grupos-categorias.index')
            ->with('flash.success', 'Grupo creado.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GrupoCategoria $grupoCategoria): Response
    {
        $this->authorize('update', $grupoCategoria);

        return Inertia::render('GruposCategorias/Edit', [
            'grupo' => $grupoCategoria,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * presupuesto_id es fijo tras crear: UpdateGrupoCategoriaRequest ni
     * siquiera lo valida, así que $request->validated() nunca lo incluye.
     */
    public function update(UpdateGrupoCategoriaRequest $request, GrupoCategoria $grupoCategoria): RedirectResponse
    {
        $this->authorize('update', $grupoCategoria);

        $grupoCategoria->update($request->validated());

        return redirect()->route('grupos-categorias.index')
            ->with('flash.success', 'Grupo actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * El hook deleting() del modelo cascadea el soft delete a las categorías
     * hijas (ver app/Models/GrupoCategoria.php).
     */
    public function destroy(GrupoCategoria $grupoCategoria): RedirectResponse
    {
        $this->authorize('delete', $grupoCategoria);

        $grupoCategoria->delete();

        return redirect()->route('grupos-categorias.index')
            ->with('flash.info', 'Grupo eliminado.');
    }

    /**
     * Presupuesto activo del usuario autenticado, o un redirect a /dashboard
     * con flash de aviso si no tiene ninguno. Mismo patrón que
     * CuentaController (Change 4).
     */
    private function presupuestoActivoOrRedirect(): Presupuesto|RedirectResponse
    {
        $activo = auth()->user()->presupuestoActivo;

        return $activo ?? redirect()->route('dashboard')
            ->with('flash.danger', 'Necesitas un presupuesto activo para gestionar categorías.');
    }
}
