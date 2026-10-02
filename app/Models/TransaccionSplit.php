<?php

namespace App\Models;

use Database\Factories\TransaccionSplitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransaccionSplit extends Model
{
    /** @use HasFactory<TransaccionSplitFactory> */
    use HasFactory;

    use SoftDeletes;

    // $table explícito: Str::plural(Str::snake('TransaccionSplit')) =
    // 'transaccion_splits', no 'transacciones_split' (el nombre real de la
    // tabla) — verificado con tinker antes de omitirlo.
    protected $table = 'transacciones_split';

    protected $fillable = [
        'transaccion_id',
        'categoria_id',
        'monto_centavos',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'monto_centavos' => 'integer',
        ];
    }

    public function transaccion(): BelongsTo
    {
        return $this->belongsTo(Transaccion::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
