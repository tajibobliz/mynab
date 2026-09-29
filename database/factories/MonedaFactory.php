<?php

namespace Database\Factories;

use App\Models\Moneda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Las monedas reales (BOB/USD/USDT) se crean en la migración, no aquí.
 * Esta factory sirve para tests que necesitan una moneda ad-hoc distinta
 * del catálogo real (p. ej. probar el scope `activas()` con una inactiva).
 *
 * @extends Factory<Moneda>
 */
class MonedaFactory extends Factory
{
    protected $model = Moneda::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->lexify('???')),
            'nombre' => fake()->currencyCode(),
            'simbolo' => fake()->randomElement(['$', '€', '£', 'Bs.']),
            'decimales' => 2,
            'activa' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activa' => false]);
    }
}
