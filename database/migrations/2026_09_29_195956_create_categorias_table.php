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
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_categoria_id')->constrained('grupos_categorias')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->index('grupo_categoria_id');

            // Sin unique(grupo_categoria_id, nombre) a nivel DB: mismo motivo
            // que grupos_categorias — unicidad case-insensitive vive en el
            // FormRequest.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
