<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;

class CategoriaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * StoreCategoriaRequest ya validó que grupo_categoria_id pertenece al
     * presupuesto activo del usuario (previene IDOR, ver el Request).
     */
    public function store(StoreCategoriaRequest $request): RedirectResponse
    {
        Categoria::create($request->validated());

        return redirect()->route('grupos-categorias.index')
            ->with('flash.success', 'Categoría creada.');
    }

    /**
     * Update the specified resource in storage.
     *
     * load('grupoCategoria.presupuesto') antes de authorize(): la policy hace
     * 2 saltos de relación (categoria -> grupoCategoria -> presupuesto); sin
     * precargar, cada check dispara 2 queries lazy adicionales.
     */
    public function update(UpdateCategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $categoria->load('grupoCategoria.presupuesto');
        $this->authorize('update', $categoria);

        $categoria->update($request->validated());

        return redirect()->route('grupos-categorias.index')
            ->with('flash.success', 'Categoría actualizada.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        $categoria->load('grupoCategoria.presupuesto');
        $this->authorize('delete', $categoria);

        $categoria->delete();

        return redirect()->route('grupos-categorias.index')
            ->with('flash.info', 'Categoría eliminada.');
    }
}
