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
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->unsignedSmallInteger('año');
            $table->unsignedTinyInteger('mes');
            $table->bigInteger('monto_centavos');
            $table->timestamps();

            // Sin softDeletes: borrar una asignación significa "no asigné
            // nada" (equivalente a monto 0), no un estado a preservar
            // (design.md Decisión 1).
            $table->unique(['presupuesto_id', 'categoria_id', 'año', 'mes']);
            $table->index(['presupuesto_id', 'año', 'mes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
