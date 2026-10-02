<?php

namespace Database\Factories;

use App\Enums\TipoTransaccion;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\Transaccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaccion>
 */
class TransaccionFactory extends Factory
{
    protected $model = Transaccion::class;

    /**
     * Define the model's default state.
     *
     * Default es un outflow simple (el caso más común): cuenta + categoría,
     * sin beneficiario ni cuenta_destino. Los estados ajustan lo que cambia
     * por tipo, en vez de repetir el shape completo en cada uno.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $montoCentavos = fake()->numberBetween(1000, 50000);

        return [
            'cuenta_id' => Cuenta::factory(),
            'cuenta_destino_id' => null,
            'categoria_id' => Categoria::factory(),
            'beneficiario_id' => null,
            'tipo' => TipoTransaccion::Outflow->value,
            'monto_centavos' => $montoCentavos,
            'monto_moneda_base_centavos' => $montoCentavos,
            'monto_centavos_destino' => null,
            'tasa_cambio_aplicada' => null,
            'fecha_hora' => fake()->dateTimeBetween('-20 days', 'now'),
            'notas' => null,
            'es_split' => false,
        ];
    }

    public function outflow(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoTransaccion::Outflow->value,
            'cuenta_destino_id' => null,
            'es_split' => false,
        ]);
    }

    public function inflow(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoTransaccion::Inflow->value,
            'categoria_id' => null,
            'cuenta_destino_id' => null,
            'es_split' => false,
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoTransaccion::Transfer->value,
            'categoria_id' => null,
            'beneficiario_id' => null,
            'cuenta_destino_id' => Cuenta::factory(),
            'es_split' => false,
        ]);
    }

    /**
     * Split es exclusivo de outflow (design.md Decisión 6): categoria_id del
     * padre queda null, la info real vive en los TransaccionSplit hijos
     * (crearlos es responsabilidad de quien use este estado, no del factory).
     */
    public function split(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoTransaccion::Outflow->value,
            'categoria_id' => null,
            'cuenta_destino_id' => null,
            'es_split' => true,
        ]);
    }
}
