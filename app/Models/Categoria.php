<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
