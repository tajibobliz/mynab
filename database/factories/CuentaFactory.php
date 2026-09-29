<?php

namespace Database\Factories;

use App\Enums\TipoCuenta;
use App\Models\Cuenta;
use App\Models\Presupuesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cuenta>
 */
class CuentaFactory extends Factory
{
    protected $model = Cuenta::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'presupuesto_id' => Presupuesto::factory(),
            'moneda_codigo' => fake()->randomElement(['BOB', 'USD', 'USDT']),
            'nombre' => fake()->company(),
            'tipo' => fake()->randomElement(TipoCuenta::cases())->value,
            'saldo_inicial_centavos' => fake()->numberBetween(0, 10_000_000),
            'fecha_apertura' => fake()->dateTimeBetween('-2 years', 'now'),
            'numero_referencia' => fake()->boolean(50) ? fake()->numerify('**####') : null,
        ];
    }

    public function banco(): static
    {
        return $this->state(fn () => ['tipo' => TipoCuenta::Banco->value]);
    }

    public function efectivo(): static
    {
        return $this->state(fn () => ['tipo' => TipoCuenta::Efectivo->value]);
    }

    public function wallet(): static
    {
        return $this->state(fn () => ['tipo' => TipoCuenta::Wallet->value]);
    }
}
