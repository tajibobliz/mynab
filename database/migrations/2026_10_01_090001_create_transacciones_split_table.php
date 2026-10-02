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
        Schema::create('transacciones_split', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaccion_id')->constrained('transacciones')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->bigInteger('monto_centavos');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('transaccion_id');
            $table->index('categoria_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transacciones_split');
    }
};
