<?php

namespace App\Models;

use Database\Factories\PresupuestoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Presupuesto extends Model
{
    /** @use HasFactory<PresupuestoFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'nombre',
        'descripcion',
        'color',
        'icono',
        'moneda_base_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
