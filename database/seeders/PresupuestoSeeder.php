<?php

namespace Database\Seeders;

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
        ]);

        $maria->presupuestos()->create([
            'nombre' => 'Freelance USD',
            'color' => '#8b5cf6',
            'icono' => 'briefcase',
        ]);

        $maria->update(['presupuesto_activo_id' => $personal->id]);
    }
}
