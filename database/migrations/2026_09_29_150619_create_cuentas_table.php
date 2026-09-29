<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained()->cascadeOnDelete();
            // string(5), no char(5): mismo motivo que monedas.codigo (Change 3)
            // — Postgres rellena CHAR con espacios al leerlo.
            $table->string('moneda_codigo', 5);
            $table->string('nombre', 100);
            $table->string('tipo', 20);
            $table->bigInteger('saldo_inicial_centavos')->default(0);
            $table->date('fecha_apertura');
            $table->string('numero_referencia', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('moneda_codigo')->references('codigo')->on('monedas')->restrictOnDelete();
            $table->index(['presupuesto_id', 'tipo']);

            // Sin unique(presupuesto_id, nombre) a nivel DB: la unicidad es
            // case-insensitive (LOWER(nombre)) y se aplica en el FormRequest
            // (mismo patrón que presupuestos.nombre, Change 2) — un unique de
            // Postgres sería case-sensitive y entraría en conflicto.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuentas');
    }
};
