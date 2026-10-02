<?php

use App\Models\TransaccionSplit;
use App\Services\CalculadoraTransaccionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transacciones_split', function (Blueprint $table) {
            $table->bigInteger('monto_moneda_base_centavos')->nullable()->after('monto_centavos');
        });

        // Backfill con el service real (no una copia 1:1 ni una tasa a mano):
        // los splits existentes del seeder resultan ser caso identidad (BOB en
        // cuenta BOB, presupuesto base BOB), pero usar el service real deja el
        // backfill correcto también si alguna vez hubiera splits multi-moneda
        // reales en una base ya migrada (modificación cruzada mínima a Change
        // 7, aprobada — ver design.md Decisión 2).
        $service = app(CalculadoraTransaccionService::class);

        TransaccionSplit::withTrashed()
            ->with('transaccion.cuenta.presupuesto')
            ->get()
            ->each(function (TransaccionSplit $split) use ($service) {
                $cuenta = $split->transaccion->cuenta;
                $presupuesto = $cuenta->presupuesto;

                [$montoBase] = $service->resolverMontoMonedaBase(
                    $split->monto_centavos,
                    $cuenta->moneda_codigo,
                    $presupuesto->moneda_base_codigo,
                    $presupuesto->user_id,
                    $split->transaccion->fecha_hora,
                );

                // forceFill(), no update(): en este punto del change
                // TransaccionSplit::$fillable TODAVÍA no incluye esta columna
                // (eso es Grupo 2) — update() la habría descartado en
                // silencio por mass assignment, dejando la columna en null y
                // reventando el ALTER ... SET NOT NULL de más abajo (bug real
                // detectado al correr esta migración antes de escribir Grupo
                // 2, no un caso hipotético).
                $split->forceFill(['monto_moneda_base_centavos' => $montoBase])->save();
            });

        // doctrine/dbal no es dependencia directa (mismo motivo que Change 3):
        // se evita ->change() y se aplica el NOT NULL con SQL crudo. SQLite
        // (driver de los tests) no soporta ALTER COLUMN ... SET NOT NULL —
        // ahí no hace falta: el controller y el backfill ya garantizan el
        // valor a nivel de aplicación, y Postgres (desarrollo real) sí aplica
        // el constraint a nivel de motor.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE transacciones_split ALTER COLUMN monto_moneda_base_centavos SET NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transacciones_split', function (Blueprint $table) {
            $table->dropColumn('monto_moneda_base_centavos');
        });
    }
};
