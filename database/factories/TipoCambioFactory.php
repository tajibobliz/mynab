<?php

namespace Database\Factories;

use App\Models\Moneda;
use App\Models\TipoCambio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoCambio>
 */
class TipoCambioFactory extends Factory
{
    protected $model = TipoCambio::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Códigos reales del catálogo (sembrados en la migración de monedas).
        // Se consultan en vez de crear monedas nuevas: más rápido y evita
        // inflar la tabla `monedas` con códigos aleatorios en cada test.
        $codigos = Moneda::pluck('codigo')->all();

        if (count($codigos) < 2) {
            $codigos = [
                Moneda::factory()->create()->codigo,
                Moneda::factory()->create()->codigo,
            ];
        }

        // randomElements sin reemplazo: garantiza origen !== destino,
        // requisito crítico (check constraint en Postgres, validación de
        // aplicación en SQLite/tests) — no se puede dejar al azar simple.
        [$origen, $destino] = fake()->randomElements($codigos, 2);

        return [
            'user_id' => User::factory(),
            'moneda_origen' => $origen,
            'moneda_destino' => $destino,
            'tasa' => fake()->numberBetween(1, 999) / 100,
            'fecha' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
