<?php

use App\Http\Controllers\BeneficiarioController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\GrupoCategoriaController;
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

    // Mismo bug de tipos-cambio: Str::singular('grupos-categorias') solo le
    // quita la 's' a "categorias" ("grupos" queda plural), así que el
    // wildcard por defecto sería {grupos_categoria} (plural-singular) y no
    // calzaría con GrupoCategoriaController::update(GrupoCategoria
    // $grupoCategoria) (cuyo Str::snake es 'grupo_categoria', singular-singular).
    // Verificado con tinker antes de escribir esto (Grupo 7 del plan).
    Route::resource('grupos-categorias', GrupoCategoriaController::class)
        ->except('show')
        ->parameters(['grupos-categorias' => 'grupo_categoria']);

    // "categorias" no tiene guión -> Str::singular('categorias') = 'categoria'
    // sin ambigüedad, coincide con Categoria $categoria. Sin ->parameters().
    Route::resource('categorias', CategoriaController::class)
        ->only(['store', 'update', 'destroy']);

    // "beneficiarios" tampoco tiene guión -> Str::singular('beneficiarios')
    // = 'beneficiario' sin ambigüedad, coincide con BeneficiarioController
    // ::update(Beneficiario $beneficiario). Sin ->parameters(). Verificado
    // con route:list -v y con un dispatch real antes de confiar en esto
    // (mismo rigor que el bug de tipos-cambio, aunque aqui no habia guion).
    Route::resource('beneficiarios', BeneficiarioController::class)->except('show');
});
