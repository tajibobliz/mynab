<?php

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
        // Postgres no permite agregar+poblar+forzar NOT NULL en un solo
        // Schema::table — se hace en pasos separados.
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->string('moneda_base_codigo', 5)->nullable()->after('moneda_base_id');
        });

        // En Change 2, moneda_base_id nunca se llegó a poblar (no había UI
        // para setearlo), así que esto es una migración de datos "vacíos" en
        // la práctica — pero se hace por nombre para no perder la intención
        // real si alguna vez sí hubo datos.
        //
        // ILIKE es específico de Postgres y no existe en SQLite (el driver
        // de los tests, ver phpunit.xml). LOWER()+LIKE es portable entre
        // ambos motores — mismo patrón que la regla de unicidad
        // case-insensitive de StorePresupuestoRequest (Change 2).
        DB::table('presupuestos')
            ->whereNull('moneda_base_codigo')
            ->where(function ($query) {
                $query->whereRaw('LOWER(nombre) LIKE ?', ['%usd%'])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', ['%dolar%'])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', ['%dólar%']);
            })
            ->update(['moneda_base_codigo' => 'USD']);

        DB::table('presupuestos')
            ->whereNull('moneda_base_codigo')
            ->update(['moneda_base_codigo' => 'BOB']);

        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn('moneda_base_id');
        });

        // doctrine/dbal no es dependencia directa del proyecto, así que se
        // evita ->change() y se aplica el NOT NULL con SQL crudo. SQLite (el
        // driver de los tests) no soporta ALTER COLUMN ... SET NOT NULL de
        // ninguna forma — ahí no hace falta: StorePresupuestoRequest ya
        // exige el campo a nivel de aplicación, y Postgres (producción y
        // desarrollo real) sí aplica el constraint a nivel de motor.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE presupuestos ALTER COLUMN moneda_base_codigo SET NOT NULL');
        }

        Schema::table('presupuestos', function (Blueprint $table) {
            $table->foreign('moneda_base_codigo')
                ->references('codigo')->on('monedas')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropForeign(['moneda_base_codigo']);
            $table->dropColumn('moneda_base_codigo');
            $table->unsignedBigInteger('moneda_base_id')->nullable();
        });
    }
};
