<?php

namespace App\Http\Controllers;

use App\Enums\TipoTransaccion;
use App\Http\Requests\StoreTransaccionRequest;
use App\Http\Requests\UpdateTransaccionRequest;
use App\Models\Cuenta;
use App\Models\Presupuesto;
use App\Models\Transaccion;
use App\Services\CalculadoraTransaccionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TransaccionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $activo = $this->presupuestoActivoOrRedirect();
        if ($activo instanceof RedirectResponse) {
            return $activo;
        }

        $query = Transaccion::whereHas('cuenta', fn ($q) => $q->where('presupuesto_id', $activo->id))
            ->with(['cuenta.moneda', 'cuentaDestino', 'categoria.grupoCategoria', 'beneficiario', 'splits.categoria.grupoCategoria'])
            ->orderByDesc('fecha_hora');

        $query = $this->aplicarFiltros($query, $request);

        return Inertia::render('Transacciones/Index', [
            'transacciones' => $query->cursorPaginate(50)->withQueryString(),
            'filtros' => $request->only(['tipo', 'cuenta', 'categoria', 'beneficiario', 'desde', 'hasta']),
            'tiposTransaccion' => TipoTransaccion::opciones(),
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

        return Inertia::render('Transacciones/Create', [
            'presupuestoActivo' => $activo,
            'tiposTransaccion' => TipoTransaccion::opciones(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransaccionRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        DB::transaction(function () use ($datos) {
            $cuenta = Cuenta::findOrFail($datos['cuenta_id']);
            $presupuesto = $cuenta->presupuesto;
            $service = app(CalculadoraTransaccionService::class);
            $fechaHora = Carbon::parse($datos['fecha_hora']);

            [$montoMonedaBase, $tasa] = $service->resolverMontoMonedaBase(
                $datos['monto_centavos'],
                $cuenta->moneda_codigo,
                $presupuesto->moneda_base_codigo,
                auth()->id(),
                $fechaHora,
            );

            $montoDestino = null;
            if ($datos['tipo'] === TipoTransaccion::Transfer->value) {
                $cuentaDestino = Cuenta::findOrFail($datos['cuenta_destino_id']);
                $montoDestino = $service->resolverMontoDestino(
                    $datos['monto_centavos'],
                    $cuenta->moneda_codigo,
                    $cuentaDestino->moneda_codigo,
                    auth()->id(),
                    $fechaHora,
                );
            }

            $transaccion = Transaccion::create([
                'cuenta_id' => $datos['cuenta_id'],
                'cuenta_destino_id' => $datos['cuenta_destino_id'] ?? null,
                'categoria_id' => $datos['categoria_id'] ?? null,
                'beneficiario_id' => $datos['beneficiario_id'] ?? null,
                'tipo' => $datos['tipo'],
                'monto_centavos' => $datos['monto_centavos'],
                'monto_moneda_base_centavos' => $montoMonedaBase,
                'monto_centavos_destino' => $montoDestino,
                'tasa_cambio_aplicada' => $tasa,
                'fecha_hora' => $fechaHora,
                'notas' => $datos['notas'] ?? null,
                'es_split' => $datos['es_split'] ?? false,
            ]);

            foreach ($datos['splits'] ?? [] as $split) {
                $transaccion->splits()->create([
                    'categoria_id' => $split['categoria_id'],
                    'monto_centavos' => $split['monto_centavos'],
                    'notas' => $split['notas'] ?? null,
                ]);
            }
        });

        return redirect()->route('transacciones.index')
            ->with('flash.success', 'Transacción creada.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transaccion $transaccion): Response
    {
        $transaccion->load('cuenta.presupuesto');
        $this->authorize('update', $transaccion);

        $transaccion->load(['cuentaDestino', 'categoria', 'beneficiario', 'splits.categoria']);

        return Inertia::render('Transacciones/Edit', [
            'transaccion' => $transaccion,
            'tiposTransaccion' => TipoTransaccion::opciones(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * tipo y es_split son fijos tras crear (design.md Decisión 6 / Fase 2).
     * UpdateTransaccionRequest ni siquiera los valida, pero esto es defensa
     * en profundidad por si llegaran en el payload raw.
     *
     * Reemplazo completo de splits en vez de diff incremental (decisión
     * explícita del plan, hallazgo 7): soft-delete de todos los existentes +
     * recreación desde el payload validado. Más simple y correcto que
     * diffear líneas, el histórico se preserva vía soft delete.
     */
    public function update(UpdateTransaccionRequest $request, Transaccion $transaccion): RedirectResponse
    {
        $transaccion->load('cuenta.presupuesto');
        $this->authorize('update', $transaccion);

        $datos = array_diff_key($request->validated(), [
            'tipo' => 0,
            'es_split' => 0,
        ]);

        DB::transaction(function () use ($transaccion, $datos) {
            $cuenta = Cuenta::findOrFail($datos['cuenta_id']);
            $presupuesto = $cuenta->presupuesto;
            $service = app(CalculadoraTransaccionService::class);
            $fechaHora = Carbon::parse($datos['fecha_hora']);

            [$montoMonedaBase, $tasa] = $service->resolverMontoMonedaBase(
                $datos['monto_centavos'],
                $cuenta->moneda_codigo,
                $presupuesto->moneda_base_codigo,
                auth()->id(),
                $fechaHora,
            );

            $montoDestino = null;
            if ($transaccion->tipo === TipoTransaccion::Transfer) {
                $cuentaDestino = Cuenta::findOrFail($datos['cuenta_destino_id']);
                $montoDestino = $service->resolverMontoDestino(
                    $datos['monto_centavos'],
                    $cuenta->moneda_codigo,
                    $cuentaDestino->moneda_codigo,
                    auth()->id(),
                    $fechaHora,
                );
            }

            $transaccion->update([
                'cuenta_id' => $datos['cuenta_id'],
                'cuenta_destino_id' => $datos['cuenta_destino_id'] ?? null,
                'categoria_id' => $datos['categoria_id'] ?? null,
                'beneficiario_id' => $datos['beneficiario_id'] ?? null,
                'monto_centavos' => $datos['monto_centavos'],
                'monto_moneda_base_centavos' => $montoMonedaBase,
                'monto_centavos_destino' => $montoDestino,
                'tasa_cambio_aplicada' => $tasa,
                'fecha_hora' => $fechaHora,
                'notas' => $datos['notas'] ?? null,
            ]);

            if ($transaccion->es_split) {
                $transaccion->splits()->each(fn ($split) => $split->delete());

                foreach ($datos['splits'] ?? [] as $split) {
                    $transaccion->splits()->create([
                        'categoria_id' => $split['categoria_id'],
                        'monto_centavos' => $split['monto_centavos'],
                        'notas' => $split['notas'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('transacciones.index')
            ->with('flash.success', 'Transacción actualizada.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaccion $transaccion): RedirectResponse
    {
        $transaccion->load('cuenta.presupuesto');
        $this->authorize('delete', $transaccion);

        $transaccion->delete();

        return redirect()->route('transacciones.index')
            ->with('flash.info', 'Transacción eliminada.');
    }

    /**
     * Filtros opcionales combinables (design.md Decisión 10). Sin desde/hasta
     * explícitos, default a los últimos 30 días.
     */
    private function aplicarFiltros(Builder $query, Request $request): Builder
    {
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('cuenta')) {
            $query->where('cuenta_id', $request->integer('cuenta'));
        }

        if ($request->filled('categoria')) {
            $categoriaId = $request->integer('categoria');
            $query->where(fn ($q) => $q
                ->where('categoria_id', $categoriaId)
                ->orWhereHas('splits', fn ($s) => $s->where('categoria_id', $categoriaId)));
        }

        if ($request->filled('beneficiario')) {
            $query->where('beneficiario_id', $request->integer('beneficiario'));
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha_hora', '>=', $request->date('desde'));
        }

        if ($request->filled('hasta')) {
            $query->whereDate('fecha_hora', '<=', $request->date('hasta'));
        } elseif (! $request->filled('desde')) {
            $query->whereDate('fecha_hora', '>=', now()->subDays(30));
        }

        return $query;
    }

    /**
     * Presupuesto activo del usuario autenticado, o un redirect a /dashboard
     * con flash de aviso si no tiene ninguno. Mismo patrón que
     * Cuenta/GrupoCategoria/BeneficiarioController.
     */
    private function presupuestoActivoOrRedirect(): Presupuesto|RedirectResponse
    {
        $activo = auth()->user()->presupuestoActivo;

        return $activo ?? redirect()->route('dashboard')
            ->with('flash.danger', 'Necesitas un presupuesto activo para gestionar transacciones.');
    }
}
