<?php

namespace App\Models;

use Database\Factories\TipoCambioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoCambio extends Model
{
    /** @use HasFactory<TipoCambioFactory> */
    use HasFactory;

    // Eloquent adivina el nombre de tabla pluralizando en inglés
    // ("TipoCambio" -> "tipo_cambios", "s" al final). La tabla real es
    // "tipos_cambio" (plural español, "s" al inicio, como pide CLAUDE.md).
    protected $table = 'tipos_cambio';

    protected $fillable = [
        'user_id',
        'moneda_origen',
        'moneda_destino',
        'tasa',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tasa' => 'decimal:8',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monedaOrigen(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_origen', 'codigo');
    }

    public function monedaDestino(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_destino', 'codigo');
    }
}
