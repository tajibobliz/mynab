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

        // 4 grupos + 10 categorías del escenario oficial, todos en Personal.
        // Freelance USD queda sin grupos a propósito (Grupo 12: empty state).
        $obligaciones = $personal->gruposCategorias()->create([
            'nombre' => 'Obligaciones inmediatas',
            'color' => '#ef4444',
            'icono' => 'AlertCircle',
        ]);
        $obligaciones->categorias()->createMany([
            ['nombre' => 'Alquiler'],
            ['nombre' => 'Transporte'],
            ['nombre' => 'Comida básica'],
        ]);

        $gastosReales = $personal->gruposCategorias()->create([
            'nombre' => 'Gastos reales',
            'color' => '#f59e0b',
            'icono' => 'Sparkles',
        ]);
        $gastosReales->categorias()->createMany([
            ['nombre' => 'Ropa'],
            ['nombre' => 'Cortes de pelo'],
            ['nombre' => 'Regalos'],
        ]);

        $calidadVida = $personal->gruposCategorias()->create([
            'nombre' => 'Calidad de vida',
            'color' => '#8b5cf6',
            'icono' => 'Music',
        ]);
        $calidadVida->categorias()->createMany([
            ['nombre' => 'Salidas'],
            ['nombre' => 'Suscripciones'],
        ]);

        $ahorros = $personal->gruposCategorias()->create([
            'nombre' => 'Ahorros',
            'color' => '#22c55e',
            'icono' => 'PiggyBank',
        ]);
        $ahorros->categorias()->createMany([
            ['nombre' => 'Emergencia'],
            ['nombre' => 'Viajes'],
        ]);

        // 8 beneficiarios del escenario oficial, todos en Personal. Freelance
        // USD queda sin beneficiarios a propósito (Grupo 11: empty state).
        $personal->beneficiarios()->createMany([
            ['nombre' => 'Dueño del alquiler', 'notas' => null],
            ['nombre' => 'SIM Entel', 'notas' => null],
            ['nombre' => 'Netflix', 'notas' => null],
            ['nombre' => 'Spotify', 'notas' => null],
            ['nombre' => 'Supermercado Hipermaxi', 'notas' => null],
            ['nombre' => 'Mi barbero', 'notas' => 'Avenida Beni, cerca del semáforo'],
            ['nombre' => 'Empresa X (sueldo)', 'notas' => null],
            ['nombre' => 'Cliente freelance A', 'notas' => null],
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
