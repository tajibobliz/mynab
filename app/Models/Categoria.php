<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    use SoftDeletes;

    // Sin $table explícito: Str::snake('Categoria') = 'categoria', su plural
    // inglés coincide con el español ('categorias') — a diferencia de
    // GrupoCategoria, aquí la inferencia por defecto de Eloquent es correcta.
    // Verificado con tinker antes de omitirlo (no asumido).

    protected $fillable = [
        'grupo_categoria_id',
        'nombre',
    ];

    public function grupoCategoria(): BelongsTo
    {
        return $this->belongsTo(GrupoCategoria::class);
    }

    /**
     * Transacciones NO split con esta categoría directa. Las transacciones
     * split guardan la categoría en transacciones_split, no aquí — Change 8
     * necesitará unir ambas fuentes para el "Activity" total por categoría.
     */
    public function transacciones(): HasMany
    {
        return $this->hasMany(Transaccion::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransaccionSplit::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }
}
