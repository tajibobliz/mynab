<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTipoCambioRequest;
use App\Http\Requests\UpdateTipoCambioRequest;
use App\Models\Moneda;
use App\Models\TipoCambio;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoCambioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Devuelve la lista plana (con relaciones cargadas) ordenada por fecha
     * descendente; el agrupamiento visual por par (BOB↔USDT, etc.) se hace
     * en el frontend con un `computed` (Grupo 10), no aquí.
     */
    public function index(Request $request): Response
    {
        $query = TipoCambio::where('user_id', auth()->id())
            ->with(['monedaOrigen', 'monedaDestino'])
            ->orderBy('fecha', 'desc');

        if ($request->filled('origen') && $request->filled('destino')) {
            $query->where('moneda_origen', $request->input('origen'))
                ->where('moneda_destino', $request->input('destino'));
        }

        return Inertia::render('TiposCambio/Index', [
            'tiposCambio' => $query->get()->map($this->formatear(...)),
            'filtro' => $request->only(['origen', 'destino']),
        ]);
    }

    /**
     * Da forma explícita al JSON enviado a Inertia. Necesario porque
     * `Str::snake('monedaOrigen')` === 'moneda_origen': al hacer
     * `->with(['monedaOrigen'])`, Eloquent serializa esa relación bajo la
     * misma clave que la columna string real y la pisa
     * (`array_merge(attributesToArray(), relationsToArray())`, relations
     * gana). Verificado con tinker antes de escribir esto. `getRawOriginal`
     * recupera el código de la columna sin pasar por la relación.
     */
    private function formatear(TipoCambio $tipoCambio): array
    {
        return [
            'id' => $tipoCambio->id,
            'moneda_origen' => $tipoCambio->getRawOriginal('moneda_origen'),
            'moneda_destino' => $tipoCambio->getRawOriginal('moneda_destino'),
            'origen' => $this->resumenMoneda($tipoCambio->monedaOrigen),
            'destino' => $this->resumenMoneda($tipoCambio->monedaDestino),
            'tasa' => $tipoCambio->tasa,
            'fecha' => $tipoCambio->fecha->toDateString(),
        ];
    }

    /**
     * @return array{codigo: string, nombre: string, simbolo: string}|null
     */
    private function resumenMoneda(?Moneda $moneda): ?array
    {
        if ($moneda === null) {
            return null;
        }

        return [
            'codigo' => $moneda->codigo,
            'nombre' => $moneda->nombre,
            'simbolo' => $moneda->simbolo,
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('TiposCambio/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTipoCambioRequest $request)
    {
        auth()->user()->tiposCambio()->create($request->validated());

        return redirect()->route('tipos-cambio.index')
            ->with('flash.success', 'Tipo de cambio registrado.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TipoCambio $tipoCambio): Response
    {
        $this->authorize('update', $tipoCambio);

        $tipoCambio->load(['monedaOrigen', 'monedaDestino']);

        return Inertia::render('TiposCambio/Edit', [
            'tipoCambio' => $this->formatear($tipoCambio),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * Solo tasa/fecha son editables: UpdateTipoCambioRequest ni siquiera
     * valida moneda_origen/moneda_destino, así que aunque el form los mande
     * (disabled), `validated()` nunca los incluye.
     */
    public function update(UpdateTipoCambioRequest $request, TipoCambio $tipoCambio)
    {
        $this->authorize('update', $tipoCambio);

        $tipoCambio->update($request->validated());

        return redirect()->route('tipos-cambio.index')
            ->with('flash.success', 'Tipo de cambio actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TipoCambio $tipoCambio)
    {
        $this->authorize('delete', $tipoCambio);

        $tipoCambio->delete();

        return redirect()->route('tipos-cambio.index')
            ->with('flash.success', 'Tipo de cambio eliminado.');
    }
}
