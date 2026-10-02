<?php

namespace App\Models;

use Database\Factories\AsignacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asignacion extends Model
{
    /** @use HasFactory<AsignacionFactory> */
    use HasFactory;

    // $table explícito: Str::plural(Str::snake('Asignacion')) = 'asignacions'
    // (el inflector en inglés no reconoce "asignación/asignacion" y solo
    // agrega 's'), no 'asignaciones' (el nombre real de la tabla) —
    // verificado con tinker en Grupo 1, mismo patrón que Transaccion
    // (Change 7). Sin soft delete: borrar una asignación equivale a monto 0
    // (design.md Decisión 1).
    protected $table = 'asignaciones';

    protected $fillable = [
        'presupuesto_id',
        'categoria_id',
        'año',
        'mes',
        'monto_centavos',
    ];

    protected function casts(): array
    {
        return [
            'año' => 'integer',
            'mes' => 'integer',
            'monto_centavos' => 'integer',
        ];
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
