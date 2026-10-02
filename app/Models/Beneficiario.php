<?php

namespace App\Models;

use Database\Factories\BeneficiarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficiario extends Model
{
    /** @use HasFactory<BeneficiarioFactory> */
    use HasFactory;

    use SoftDeletes;

    // Sin $table: Str::snake('Beneficiario') = 'beneficiario', plural
    // 'beneficiarios' coincide con la tabla — verificado con tinker antes de
    // escribir esto (mismo caso que Categoria, a diferencia de GrupoCategoria).
    protected $fillable = ['presupuesto_id', 'nombre', 'notas'];

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function transacciones(): HasMany
    {
        return $this->hasMany(Transaccion::class);
    }
}
