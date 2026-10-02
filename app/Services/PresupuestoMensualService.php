<?php

namespace App\Services;

use App\Enums\TipoTransaccion;
use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use App\Models\Transaccion;
use App\Models\TransaccionSplit;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cálculos de zero-based budgeting (design.md Decisiones 3-5). Sin
 * dependencias inyectadas: todo lo que agrega ya viene convertido a moneda
 * base en monto_moneda_base_centavos (transacciones y transacciones_split),
 * así que a diferencia de CalculadoraTransaccionService no hace falta
 * TasaCambioService aquí.
 */
class PresupuestoMensualService
{
    /**
     * Ready to Assign = inflows acumulados - asignaciones acumuladas, ambos
     * hasta el fin del mes consultado (rollover histórico, no solo del mes).
     */
    public function calcularReadyToAssign(Presupuesto $presupuesto, int $año, int $mes): int
    {
        $finDeMes = Carbon::create($año, $mes, 1)->endOfMonth();

        $inflowsAcumulados = (int) Transaccion::whereHas('cuenta', fn (Builder $q) => $q->where('presupuesto_id', $presupuesto->id))
            ->where('tipo', TipoTransaccion::Inflow->value)
            ->whereDate('fecha_hora', '<=', $finDeMes)
            ->sum('monto_moneda_base_centavos');

        $asignacionesAcumuladas = (int) Asignacion::where('presupuesto_id', $presupuesto->id)
            ->where($this->condicionAcumulada($año, $mes))
            ->sum('monto_centavos');

        return $inflowsAcumulados - $asignacionesAcumuladas;
    }

    /**
     * Activity DEL MES (no acumulado): outflows directos + splits de esa
     * categoría en el mes exacto consultado.
     */
    public function calcularActivity(Categoria $categoria, int $año, int $mes): int
    {
        $outflowsDirectos = (int) Transaccion::where('categoria_id', $categoria->id)
            ->where('tipo', TipoTransaccion::Outflow->value)
            ->where('es_split', false)
            ->whereYear('fecha_hora', $año)
            ->whereMonth('fecha_hora', $mes)
            ->sum('monto_moneda_base_centavos');

        $splitsDelMes = (int) TransaccionSplit::where('categoria_id', $categoria->id)
            ->whereHas('transaccion', fn (Builder $q) => $q->whereYear('fecha_hora', $año)->whereMonth('fecha_hora', $mes))
            ->sum('monto_moneda_base_centavos');

        return $outflowsDirectos + $splitsDelMes;
    }

    /**
     * Available = asignado acumulado - activity acumulada, ambos hasta el
     * fin del mes consultado (rollover: lo que sobró de meses anteriores
     * queda disponible).
     */
    public function calcularAvailable(Categoria $categoria, int $año, int $mes): int
    {
        $finDeMes = Carbon::create($año, $mes, 1)->endOfMonth();

        $asignadoAcumulado = (int) Asignacion::where('categoria_id', $categoria->id)
            ->where($this->condicionAcumulada($año, $mes))
            ->sum('monto_centavos');

        $outflowsAcumulados = (int) Transaccion::where('categoria_id', $categoria->id)
            ->where('tipo', TipoTransaccion::Outflow->value)
            ->where('es_split', false)
            ->whereDate('fecha_hora', '<=', $finDeMes)
            ->sum('monto_moneda_base_centavos');

        $splitsAcumulados = (int) TransaccionSplit::where('categoria_id', $categoria->id)
            ->whereHas('transaccion', fn (Builder $q) => $q->whereDate('fecha_hora', '<=', $finDeMes))
            ->sum('monto_moneda_base_centavos');

        return $asignadoAcumulado - ($outflowsAcumulados + $splitsAcumulados);
    }

    /**
     * Vista completa para /presupuesto-mensual. Optimizado para no iterar
     * calcularActivity()/calcularAvailable() por categoría (N+1 severo con
     * muchas categorías) — en su lugar, agrega todo por categoria_id en un
     * puñado de queries de tamaño constante (no proporcional a la cantidad
     * de categorías) y compone la estructura final en PHP.
     *
     * 7 queries físicas en total (2 de ellas son el par parent+relation del
     * eager load de categorías, Laravel no las puede fusionar en 1 sin
     * perder la protección N+1): grupos, categorías (eager), asignado del
     * mes, asignado acumulado, outflows directos (del mes + acumulado en 1
     * query vía agregación condicional), splits (ídem), inflows acumulados
     * para RTA. El total de asignado acumulado del presupuesto (para RTA)
     * se deriva sumando en PHP el resultado ya obtenido por categoría, sin
     * una query adicional.
     */
    public function obtenerVistaMensual(Presupuesto $presupuesto, int $año, int $mes): array
    {
        $inicioDeMes = Carbon::create($año, $mes, 1)->startOfMonth();
        $finDeMes = Carbon::create($año, $mes, 1)->endOfMonth();

        $grupos = GrupoCategoria::where('presupuesto_id', $presupuesto->id)
            ->with(['categorias' => fn ($q) => $q->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        $categoriaIds = $grupos->flatMap(fn (GrupoCategoria $g) => $g->categorias)->pluck('id');

        $asignadoDelMes = Asignacion::where('presupuesto_id', $presupuesto->id)
            ->where('año', $año)
            ->where('mes', $mes)
            ->pluck('monto_centavos', 'categoria_id');

        // selectRaw sin tocar "año"/"mes" en SQL crudo (solo columnas ASCII):
        // el filtro acumulado se arma con query builder puro en el where().
        $asignadoAcumulado = Asignacion::where('presupuesto_id', $presupuesto->id)
            ->where($this->condicionAcumulada($año, $mes))
            ->selectRaw('categoria_id, SUM(monto_centavos) as total')
            ->groupBy('categoria_id')
            ->pluck('total', 'categoria_id');

        // Agregación condicional (del mes + acumulado en una sola query) vía
        // CASE WHEN sobre fecha_hora — columna ASCII, sin el problema de "año".
        $outflows = Transaccion::whereIn('categoria_id', $categoriaIds)
            ->where('tipo', TipoTransaccion::Outflow->value)
            ->where('es_split', false)
            ->selectRaw(
                'categoria_id,'.
                'SUM(CASE WHEN fecha_hora BETWEEN ? AND ? THEN monto_moneda_base_centavos ELSE 0 END) as del_mes,'.
                'SUM(CASE WHEN fecha_hora <= ? THEN monto_moneda_base_centavos ELSE 0 END) as acumulado',
                [$inicioDeMes, $finDeMes, $finDeMes]
            )
            ->groupBy('categoria_id')
            ->get()
            ->keyBy('categoria_id');

        // whereNull(transacciones.deleted_at) explícito: al venir de un JOIN
        // crudo, el global scope de SoftDeletes del modelo TransaccionSplit
        // NO cubre la tabla transacciones (solo la suya propia).
        $splits = TransaccionSplit::query()
            ->join('transacciones', 'transacciones.id', '=', 'transacciones_split.transaccion_id')
            ->whereIn('transacciones_split.categoria_id', $categoriaIds)
            ->whereNull('transacciones.deleted_at')
            ->selectRaw(
                'transacciones_split.categoria_id as categoria_id,'.
                'SUM(CASE WHEN transacciones.fecha_hora BETWEEN ? AND ? THEN transacciones_split.monto_moneda_base_centavos ELSE 0 END) as del_mes,'.
                'SUM(CASE WHEN transacciones.fecha_hora <= ? THEN transacciones_split.monto_moneda_base_centavos ELSE 0 END) as acumulado',
                [$inicioDeMes, $finDeMes, $finDeMes]
            )
            ->groupBy('transacciones_split.categoria_id')
            ->get()
            ->keyBy('categoria_id');

        $inflowsAcumulados = (int) Transaccion::whereHas('cuenta', fn (Builder $q) => $q->where('presupuesto_id', $presupuesto->id))
            ->where('tipo', TipoTransaccion::Inflow->value)
            ->whereDate('fecha_hora', '<=', $finDeMes)
            ->sum('monto_moneda_base_centavos');

        $asignacionesAcumuladasTotales = (int) $asignadoAcumulado->sum();
        $readyToAssign = $inflowsAcumulados - $asignacionesAcumuladasTotales;

        $gruposArray = $grupos->map(function (GrupoCategoria $grupo) use ($asignadoDelMes, $asignadoAcumulado, $outflows, $splits) {
            return [
                'id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'color' => $grupo->color,
                'icono' => $grupo->icono,
                'categorias' => $grupo->categorias->map(function (Categoria $categoria) use ($asignadoDelMes, $asignadoAcumulado, $outflows, $splits) {
                    $assigned = (int) ($asignadoDelMes[$categoria->id] ?? 0);

                    $activityDelMes = (int) ($outflows[$categoria->id]->del_mes ?? 0)
                        + (int) ($splits[$categoria->id]->del_mes ?? 0);

                    $asignadoAcum = (int) ($asignadoAcumulado[$categoria->id] ?? 0);
                    $activityAcumulada = (int) ($outflows[$categoria->id]->acumulado ?? 0)
                        + (int) ($splits[$categoria->id]->acumulado ?? 0);

                    return [
                        'id' => $categoria->id,
                        'nombre' => $categoria->nombre,
                        'assigned' => $assigned,
                        'activity' => $activityDelMes,
                        'available' => $asignadoAcum - $activityAcumulada,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'readyToAssign' => $readyToAssign,
            'mesAño' => [
                'año' => $año,
                'mes' => $mes,
                'nombreMes' => Carbon::create($año, $mes, 1)->locale('es')->isoFormat('MMMM'),
            ],
            'grupos' => $gruposArray,
        ];
    }

    /**
     * Composición (año, mes) <= (añoLimite, mesLimite) con query builder
     * puro (sin whereRaw ni citado manual del identificador "año").
     */
    private function condicionAcumulada(int $añoLimite, int $mesLimite): Closure
    {
        return fn (Builder $q) => $q
            ->where('año', '<', $añoLimite)
            ->orWhere(fn (Builder $q2) => $q2->where('año', $añoLimite)->where('mes', '<=', $mesLimite));
    }
}
