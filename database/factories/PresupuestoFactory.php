<?php

namespace Database\Factories;

use App\Models\Moneda;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presupuesto>
 */
class PresupuestoFactory extends Factory
{
    /**
     * Nombres realistas para presupuestos de ejemplo.
     */
    private const NOMBRES = [
        'Personal', 'Negocio', 'Viajes', 'Ahorros', 'Emergencias',
        'Familia', 'Freelance', 'Proyecto Personal',
    ];

    /**
     * Misma paleta que ColorPicker.vue — los datos de prueba deben poder pasar
     * por la misma UI que los datos reales, sin colores "no oficiales".
     */
    private const COLORES = [
        '#22c55e', '#8b5cf6', '#3b82f6', '#ec4899',
        '#f59e0b', '#06b6d4', '#ef4444', '#64748b',
    ];

    /**
     * Misma whitelist que config('mynab.iconos_presupuesto') / StorePresupuestoRequest.
     */
    private const ICONOS = [
        'wallet', 'briefcase', 'plane', 'gift', 'heart', 'home', 'car',
        'graduation-cap', 'utensils', 'shopping-cart', 'piggy-bank',
        'credit-card', 'coins', 'dollar-sign', 'trending-up', 'target',
        'book', 'dumbbell', 'music', 'film',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Sin ->unique(): la unicidad real es por user_id (case-insensitive),
            // no global — Faker no conoce ese scope y con solo 8 nombres fijos
            // agotaría sus reintentos y lanzaría OverflowException.
            'nombre' => fake()->randomElement(self::NOMBRES),
            'descripcion' => fake()->optional()->sentence(),
            'color' => fake()->randomElement(self::COLORES),
            'icono' => fake()->randomElement(self::ICONOS),
            // Consulta la BD real (no una lista fija en PHP) para no
            // desincronizarse si el catálogo de monedas cambia; 'BOB' es el
            // fallback si por algún motivo la tabla monedas está vacía.
            'moneda_base_codigo' => Moneda::inRandomOrder()->value('codigo') ?? 'BOB',
        ];
    }
}
