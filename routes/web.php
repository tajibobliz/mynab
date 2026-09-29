<?php

use App\Http\Controllers\CuentaController;
use App\Http\Controllers\MonedaController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\TipoCambioController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('presupuestos', PresupuestoController::class);
    Route::post('presupuestos/{presupuesto}/seleccionar', [PresupuestoController::class, 'seleccionar'])
        ->name('presupuestos.seleccionar');

    Route::get('monedas', [MonedaController::class, 'index'])->name('monedas.index');
    Route::post('monedas/{moneda}/toggle', [MonedaController::class, 'toggle'])->name('monedas.toggle');

    // ->parameters(): Str::singular('tipos-cambio') no le quita la 's' a "tipos"
    // (compuesto con guion, el inflector no lo reconoce como plural inglés), así
    // que el wildcard por defecto queda {tipos_cambio} en vez de {tipo_cambio}.
    // Eso rompía el binding implícito: TipoCambioController::update(TipoCambio
    // $tipoCambio) nunca calzaba por nombre (ni exacto ni Str::snake), Laravel
    // lo saltaba en silencio y $tipoCambio llegaba como instancia vacía sin
    // guardar -> Gate::can('update', $tipoCambio) fallaba con 403 en vez de
    // autorizar al dueño real. Verificado con Pest (Grupo 12) antes de este fix.
    Route::resource('tipos-cambio', TipoCambioController::class)
        ->except('show')
        ->parameters(['tipos-cambio' => 'tipo_cambio']);

    Route::resource('cuentas', CuentaController::class)->except('show');
});
