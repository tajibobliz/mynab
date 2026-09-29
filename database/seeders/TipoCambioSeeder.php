<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoCambioSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // PresupuestoSeeder corre antes (ver DatabaseSeeder) y ya crea a
        // María; aquí solo se le agregan tasas, no se vuelve a crear.
        $maria = User::where('email', 'maria@example.com')->firstOrFail();

        $fecha = now()->subDays(7)->toDateString();

        $tasas = [
            ['moneda_origen' => 'BOB', 'moneda_destino' => 'USDT', 'tasa' => 0.14],
            ['moneda_origen' => 'USDT', 'moneda_destino' => 'BOB', 'tasa' => 7.10],
            ['moneda_origen' => 'BOB', 'moneda_destino' => 'USD', 'tasa' => 0.145],
            ['moneda_origen' => 'USD', 'moneda_destino' => 'BOB', 'tasa' => 6.90],
            ['moneda_origen' => 'USD', 'moneda_destino' => 'USDT', 'tasa' => 0.97],
            ['moneda_origen' => 'USDT', 'moneda_destino' => 'USD', 'tasa' => 1.02],
        ];

        foreach ($tasas as $tasa) {
            $maria->tiposCambio()->create([...$tasa, 'fecha' => $fecha]);
        }
    }
}
