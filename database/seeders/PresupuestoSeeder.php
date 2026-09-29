<?php

namespace Database\Seeders;

use App\Enums\TipoCuenta;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PresupuestoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $maria = User::firstOrCreate(
            ['email' => 'maria@example.com'],
            [
                'name' => 'María Rojas',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $personal = $maria->presupuestos()->create([
            'nombre' => 'Personal',
            'color' => '#22c55e',
            'icono' => 'wallet',
            'moneda_base_codigo' => 'BOB',
        ]);

        // Escenario oficial de María (openspec/project.md). Freelance USD
        // queda sin cuentas a propósito (Grupo 12: verifica el empty state).
        $personal->cuentas()->createMany([
            [
                'moneda_codigo' => 'BOB',
                'nombre' => 'BNB Checking',
                'tipo' => TipoCuenta::Banco->value,
                'saldo_inicial_centavos' => 200000, // Bs 2,000
                'fecha_apertura' => now()->subYear(),
                'numero_referencia' => '**4417',
            ],
            [
                'moneda_codigo' => 'BOB',
                'nombre' => 'Efectivo',
                'tipo' => TipoCuenta::Efectivo->value,
                'saldo_inicial_centavos' => 30000, // Bs 300
                'fecha_apertura' => now()->subMonths(6),
                'numero_referencia' => null,
            ],
            [
                'moneda_codigo' => 'USDT',
                'nombre' => 'Binance USDT',
                'tipo' => TipoCuenta::Wallet->value,
                'saldo_inicial_centavos' => 5000, // USDT 50.00 (decimales=2)
                'fecha_apertura' => now()->subMonths(3),
                'numero_referencia' => '@maria_binance',
            ],
        ]);

        $maria->presupuestos()->create([
            'nombre' => 'Freelance USD',
            'color' => '#8b5cf6',
            'icono' => 'briefcase',
            'moneda_base_codigo' => 'USD',
        ]);

        $maria->update(['presupuesto_activo_id' => $personal->id]);
    }
}
