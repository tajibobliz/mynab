<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Transaccion;
use App\Models\TransaccionSplit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransaccionSplit>
 */
class TransaccionSplitFactory extends Factory
{
    protected $model = TransaccionSplit::class;

    /**
     * Define the model's default state.
     *
     * monto_moneda_base_centavos igual a monto_centavos por defecto (caso
     * identidad): esta columna es NOT NULL desde Change 8 (migración de
     * backfill), el factory predata esa columna (Change 7) y necesita este
     * default para no violar el constraint. Un test que quiera un caso
     * multi-moneda real lo sobreescribe explícitamente.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $montoCentavos = fake()->numberBetween(500, 20000);

        return [
            'transaccion_id' => Transaccion::factory()->split(),
            'categoria_id' => Categoria::factory(),
            'monto_centavos' => $montoCentavos,
            'monto_moneda_base_centavos' => $montoCentavos,
            'notas' => null,
        ];
    }
}
