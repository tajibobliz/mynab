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
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('moneda_origen', 5);
            $table->string('moneda_destino', 5);
            $table->decimal('tasa', 20, 8);
            $table->date('fecha');
            $table->timestamps();

            $table->foreign('moneda_origen')->references('codigo')->on('monedas')->restrictOnDelete();
            $table->foreign('moneda_destino')->references('codigo')->on('monedas')->restrictOnDelete();

            $table->unique(['user_id', 'moneda_origen', 'moneda_destino', 'fecha']);
        });

        // Blueprint::check() no existe en esta versión de Laravel, y SQLite
        // (driver de los tests) no soporta ALTER TABLE ... ADD CONSTRAINT
        // CHECK en absoluto. Mismo patrón que el NOT NULL del Grupo 2: se
        // aplica a nivel de motor solo en Postgres; StoreTipoCambioRequest
        // (Grupo 7) valida origen != destino a nivel de aplicación siempre.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tipos_cambio ADD CONSTRAINT chk_tipos_cambio_moneda_distinta CHECK (moneda_origen <> moneda_destino)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_cambio');
    }
};
