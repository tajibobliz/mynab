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
        Schema::create('grupos_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained()->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('color', 7);
            $table->string('icono', 50);
            $table->timestamps();
            $table->softDeletes();

            $table->index('presupuesto_id');

            // Sin unique(presupuesto_id, nombre) a nivel DB: la unicidad es
            // case-insensitive y se aplica en el FormRequest (mismo patrón
            // que presupuestos/cuentas — un unique de Postgres sería
            // case-sensitive y entraría en conflicto).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos_categorias');
    }
};
