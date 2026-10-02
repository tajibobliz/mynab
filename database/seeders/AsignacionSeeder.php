<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * 10 asignaciones de María para octubre 2026 (design.md Decisión 9).
 * Corre después de TransaccionSeeder: no hay dependencia de datos entre
 * ambos (las asignaciones no necesitan que existan transacciones), pero
 * sigue el mismo orden narrativo del escenario — "primero se registra lo
 * que pasó, después se presupuesta hacia adelante".
 */
class AsignacionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $maria = User::where('email', 'maria@example.com')->firstOrFail();
        $personal = $maria->presupuestos()->where('nombre', 'Personal')->firstOrFail();

        $categoriaPorNombre = fn (string $nombre) => Categoria::whereHas(
            'grupoCategoria',
            fn ($q) => $q->where('presupuesto_id', $personal->id)
        )->where('nombre', $nombre)->firstOrFail();

        $asignaciones = [
            'Alquiler' => 150000,
            'Transporte' => 30000,
            'Comida básica' => 80000,
            'Ropa' => 10000,
            'Cortes de pelo' => 6000,
            'Regalos' => 5000,
            'Salidas' => 20000,
            'Suscripciones' => 15000,
            'Emergencia' => 30000,
            'Viajes' => 15000,
        ];

        foreach ($asignaciones as $nombreCategoria => $montoCentavos) {
            $personal->asignaciones()->create([
                'categoria_id' => $categoriaPorNombre($nombreCategoria)->id,
                'año' => 2026,
                'mes' => 10,
                'monto_centavos' => $montoCentavos,
            ]);
        }
    }
}
