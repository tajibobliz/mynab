<?php

use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\BeneficiarioController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GrupoCategoriaController;
use App\Http\Controllers\MonedaController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\PresupuestoMensualController;
use App\Http\Controllers\TipoCambioController;
use App\Http\Controllers\TransaccionController;
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
    // Modificación cruzada mínima a Change 1 (gestion-jetstream-inertia-auth):
    // /dashboard era un closure inline desde el scaffolding original. Change
    // 8 lo convierte en DashboardController::index() porque ahora necesita
    // el presupuesto activo + PresupuestoMensualService para mostrar Ready
    // to Assign y los "sobres atentos" — ya no es una página estática.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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

    // CRITICO: a diferencia de categorias/beneficiarios, "transacciones" SI
    // necesita ->parameters() aunque no tenga guion. Str::singular('transacciones')
    // = 'transaccione' (el inflector en ingles trata el final "-es" como el
    // patron ingles "boxes"->"box", no reconoce el "-ciones" espanol), pero
    // Str::snake('Transaccion') = 'transaccion' (sin la "e" final). Sin este
    // fix el wildcard por defecto habria sido {transaccione}, que NUNCA
    // calzaria con TransaccionController::update(Transaccion $transaccion)
    // -> mismo bug silencioso de tipos-cambio en Change 3. Verificado con
    // tinker ANTES de asumir que "sin guion" bastaba (no bastaba).
    Route::resource('transacciones', TransaccionController::class)
        ->except('show')
        ->parameters(['transacciones' => 'transaccion']);

    // only(['store']): sin wildcard {asignacion} en la única ruta registrada,
    // el ->parameters() de abajo es un no-op hoy (no hay nada que mapear) —
    // se deja igual por si Fase 2 agrega update/destroy a este resource.
    // El $table='asignaciones' del modelo (Grupo 1/2) es lo que sí importa.
    Route::resource('asignaciones', AsignacionController::class)
        ->only(['store'])
        ->parameters(['asignaciones' => 'asignacion']);

    Route::get('/presupuesto-mensual', [PresupuestoMensualController::class, 'index'])
        ->name('presupuesto-mensual.index');
});
