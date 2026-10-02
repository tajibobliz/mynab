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
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->restrictOnDelete();
            $table->foreignId('cuenta_destino_id')->nullable()->constrained('cuentas')->restrictOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->restrictOnDelete();
            $table->foreignId('beneficiario_id')->nullable()->constrained('beneficiarios')->restrictOnDelete();
            $table->string('tipo');
            $table->bigInteger('monto_centavos');
            $table->bigInteger('monto_moneda_base_centavos');
            $table->bigInteger('monto_centavos_destino')->nullable();
            $table->decimal('tasa_cambio_aplicada', 20, 8)->nullable();
            $table->timestampTz('fecha_hora');
            $table->text('notas')->nullable();
            $table->boolean('es_split')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('cuenta_id');
            $table->index('categoria_id');
            $table->index('beneficiario_id');
            $table->index('fecha_hora');
            $table->index(['tipo', 'fecha_hora']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};
