<?php

namespace App\Models;

use App\Enums\TipoCuenta;
use Database\Factories\CuentaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * En este change retorna solo el saldo inicial: la tabla `transacciones`
     * todavía no existe. Change 7 extiende este cálculo sumando inflow/outflow
     * y transferencias (ver design.md Decisión 5 de gestion-cuentas).
     */
    public function getSaldoActualCentavosAttribute(): int
    {
        return $this->saldo_inicial_centavos;
    }

    public function saldoActualFormateado(): string
    {
        $decimales = $this->moneda->decimales;
        $valor = $this->saldo_actual_centavos / (10 ** $decimales);

        return $this->moneda->simbolo.' '.number_format($valor, $decimales, '.', '');
    }
}
