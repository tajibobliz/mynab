<?php

namespace App\Models;

use App\Enums\TipoCuenta;
use Database\Factories\CuentaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cuenta extends Model
{
    /** @use HasFactory<CuentaFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'presupuesto_id',
        'moneda_codigo',
        'nombre',
        'tipo',
        'saldo_inicial_centavos',
        'fecha_apertura',
        'numero_referencia',
    ];

    // Sin esto, el accessor de estilo "mágico" getSaldoActualCentavosAttribute()
    // NUNCA aparece en toArray()/JSON — Eloquent solo serializa accessors si
    // están en $appends, a diferencia de las columnas reales. Verificado con
    // tinker antes de construir el frontend que depende de esta prop.
    protected $appends = [
        'saldo_actual_centavos',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCuenta::class,
            'fecha_apertura' => 'date',
            'saldo_inicial_centavos' => 'integer',
        ];
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_codigo', 'codigo');
    }

    public function transacciones(): HasMany
    {
        return $this->hasMany(Transaccion::class);
    }

    public function transaccionesComoDestino(): HasMany
    {
        return $this->hasMany(Transaccion::class, 'cuenta_destino_id');
    }

    /**
     * Suma saldo inicial + inflows - outflows - transfers salientes +
     * transfers entrantes (design.md Decisión 9 de gestion-transacciones).
     * Dispara 4 queries por cuenta — N+1 potencial en el Index de Cuentas si
     * hay muchas; aceptado como backlog Fase 2 (un servicio con eager
     * loading agregado), no bloqueante para Fase 1 con pocas cuentas.
     */
    public function getSaldoActualCentavosAttribute(): int
    {
        $outflows = $this->transacciones()->where('tipo', 'outflow')->sum('monto_centavos');
        $inflows = $this->transacciones()->where('tipo', 'inflow')->sum('monto_centavos');
        $transfersOut = $this->transacciones()->where('tipo', 'transfer')->sum('monto_centavos');
        $transfersIn = $this->transaccionesComoDestino()->where('tipo', 'transfer')->sum('monto_centavos_destino');

        return $this->saldo_inicial_centavos + $inflows - $outflows - $transfersOut + $transfersIn;
    }

    public function saldoActualFormateado(): string
    {
        $decimales = $this->moneda->decimales;
        $valor = $this->saldo_actual_centavos / (10 ** $decimales);

        return $this->moneda->simbolo.' '.number_format($valor, $decimales, '.', '');
    }
}
