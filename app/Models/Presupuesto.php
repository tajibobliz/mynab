<?php

namespace App\Models;

use Database\Factories\PresupuestoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
        'moneda_base_codigo',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monedaBase(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_base_codigo', 'codigo');
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(Cuenta::class);
    }

    public function gruposCategorias(): HasMany
    {
        return $this->hasMany(GrupoCategoria::class);
    }

    public function beneficiarios(): HasMany
    {
        return $this->hasMany(Beneficiario::class);
    }

    public function transacciones(): HasManyThrough
    {
        return $this->hasManyThrough(Transaccion::class, Cuenta::class);
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class);
    }
}
