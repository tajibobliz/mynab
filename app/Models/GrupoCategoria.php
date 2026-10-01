<?php

namespace App\Models;

use Database\Factories\GrupoCategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrupoCategoria extends Model
{
    /** @use HasFactory<GrupoCategoriaFactory> */
    use HasFactory;

    use SoftDeletes;

    // Eloquent pluraliza "GrupoCategoria" -> "grupo_categoria" -> "grupo_categorias"
    // (pluralización inglesa: solo la última palabra recibe la 's'). La tabla
    // real es "grupos_categorias" (plural español, ambas palabras). Verificado
    // con tinker antes de escribir esto — mismo patrón que TipoCambio (Change 3).
    protected $table = 'grupos_categorias';

    protected $fillable = [
        'presupuesto_id',
        'nombre',
        'color',
        'icono',
    ];

    protected static function booted(): void
    {
        // El cascadeOnDelete de la FK solo aplica en HARD delete. Con soft
        // delete (el único que usa la app), hay que cascadear explícitamente
        // vía evento de Eloquent (design.md Decisión 5).
        static::deleting(function (GrupoCategoria $grupo) {
            if (! $grupo->isForceDeleting()) {
                $grupo->categorias()->each(fn (Categoria $categoria) => $categoria->delete());
            }
        });
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }
}
