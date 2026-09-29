<?php

namespace App\Models;

use Database\Factories\MonedaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Moneda extends Model
{
    /** @use HasFactory<MonedaFactory> */
    use HasFactory;

    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'simbolo',
        'decimales',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'decimales' => 'integer',
        ];
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class, 'moneda_base_codigo', 'codigo');
    }
}
