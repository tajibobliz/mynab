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
        Schema::create('monedas', function (Blueprint $table) {
            // string(5), no char(5): Postgres rellena CHAR con espacios al
            // leerlo de vuelta ('BOB' -> 'BOB  '), lo que rompe comparaciones
            // exactas en PHP (p. ej. el identity-case de TasaCambioService).
            // VARCHAR no tiene ese problema y respeta el mismo límite de 5.
            $table->string('codigo', 5)->primary();
            $table->string('nombre', 50);
            $table->string('simbolo', 5);
            $table->unsignedTinyInteger('decimales')->default(2);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Sembrado dentro de la migración (no en un seeder aparte) para que
        // exista en producción sin depender de `--seed`, y porque la FK de
        // presupuestos.moneda_base_codigo (Grupo 2) necesita que estas filas
        // ya existan.
        DB::table('monedas')->insert([
            ['codigo' => 'BOB', 'nombre' => 'Boliviano', 'simbolo' => 'Bs.', 'decimales' => 2, 'activa' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'USD', 'nombre' => 'Dólar estadounidense', 'simbolo' => '$', 'decimales' => 2, 'activa' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'USDT', 'nombre' => 'Tether USD', 'simbolo' => '₮', 'decimales' => 2, 'activa' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monedas');
    }
};
