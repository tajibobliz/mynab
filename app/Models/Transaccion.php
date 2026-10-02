<?php

namespace App\Models;

use App\Enums\TipoTransaccion;
use Database\Factories\TransaccionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaccion extends Model
{
    /** @use HasFactory<TransaccionFactory> */
    use HasFactory;

    use SoftDeletes;

    // $table explícito: Str::plural(Str::snake('Transaccion')) = 'transaccions'
    // (el inflector en inglés no reconoce "transacción/transaccion" y solo
    // agrega 's'), no 'transacciones' (el nombre real de la tabla) —
    // verificado con tinker: intentar omitirlo rompe con "undefined table
    // transaccions". El supuesto de que coincidía (como Categoria o
    // Beneficiario) era incorrecto para este sustantivo específico.
    protected $table = 'transacciones';

    protected $fillable = [
        'cuenta_id',
        'cuenta_destino_id',
        'categoria_id',
        'beneficiario_id',
        'tipo',
        'monto_centavos',
        'monto_moneda_base_centavos',
        'monto_centavos_destino',
        'tasa_cambio_aplicada',
        'fecha_hora',
        'notas',
        'es_split',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoTransaccion::class,
            'monto_centavos' => 'integer',
            'monto_moneda_base_centavos' => 'integer',
            'monto_centavos_destino' => 'integer',
            'tasa_cambio_aplicada' => 'decimal:8',
            'fecha_hora' => 'datetime',
            'es_split' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Transaccion $transaccion) {
            if (! $transaccion->isForceDeleting()) {
                $transaccion->splits()->each(fn (TransaccionSplit $split) => $split->delete());
            }
        });
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function cuentaDestino(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_destino_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransaccionSplit::class);
    }

    public function scopeDelMes(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('fecha_hora', $year)->whereMonth('fecha_hora', $month);
    }
}
