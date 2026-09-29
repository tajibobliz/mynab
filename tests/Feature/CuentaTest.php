<?php

use App\Enums\TipoCuenta;
use App\Models\Cuenta;
use App\Models\Moneda;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Crea un usuario con un presupuesto ya marcado como activo (atajo repetido
 * en casi todos los tests de este archivo).
 */
function usuarioConPresupuestoActivo(): array
{
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create();
    $user->update(['presupuesto_activo_id' => $presupuesto->id]);

    return [$user->fresh(), $presupuesto];
}

// ---------------------------------------------------------------------
// Index y acceso
// ---------------------------------------------------------------------

test('usuario autenticado ve solo cuentas de su presupuesto activo', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $otroPresupuesto = Presupuesto::factory()->for($user)->create();

    Cuenta::factory()->for($activo, 'presupuesto')->count(2)->create();
    Cuenta::factory()->for($otroPresupuesto, 'presupuesto')->create();

    $response = $this->actingAs($user)->get(route('cuentas.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Cuentas/Index')
        ->has('cuentas', 2)
    );
});

test('usuario sin presupuesto activo es redirigido a dashboard con flash', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('cuentas.index'));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('flash.danger');
});

test('create renderiza con opciones de tipo y monedas activas', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->get(route('cuentas.create'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Cuentas/Create')
        ->has('tiposCuenta', 3)
        ->has('presupuestoActivo')
        ->has('monedasActivas')
    );
});

test('un usuario no autenticado no accede a cuentas', function () {
    $response = $this->get(route('cuentas.index'));

    $response->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------

test('store crea una cuenta valida asignada al presupuesto activo', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'BNB Checking',
        'tipo' => 'banco',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 200000,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertRedirect(route('cuentas.index'));
    $this->assertDatabaseHas('cuentas', [
        'presupuesto_id' => $activo->id,
        'nombre' => 'BNB Checking',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 200000,
    ]);
});

test('store rechaza un tipo de cuenta invalido', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'X',
        'tipo' => 'credito',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('tipo');
    $this->assertDatabaseCount('cuentas', 0);
});

test('store rechaza una moneda inactiva', function () {
    [$user] = usuarioConPresupuestoActivo();
    $inactiva = Moneda::factory()->inactiva()->create();

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'X',
        'tipo' => 'banco',
        'moneda_codigo' => $inactiva->codigo,
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('moneda_codigo');
    $this->assertDatabaseCount('cuentas', 0);
});

test('store rechaza fecha de apertura futura', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'X',
        'tipo' => 'banco',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => now()->addDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('fecha_apertura');
    $this->assertDatabaseCount('cuentas', 0);
});

test('store rechaza nombre duplicado en el mismo presupuesto case-insensitive', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    Cuenta::factory()->for($activo, 'presupuesto')->create(['nombre' => 'BNB Checking']);

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'BNB CHECKING',
        'tipo' => 'banco',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('nombre');
    $this->assertDatabaseCount('cuentas', 1);
});

test('store permite nombre duplicado en otro presupuesto del mismo usuario', function () {
    $user = User::factory()->create();
    $presupuestoA = Presupuesto::factory()->for($user)->create();
    $presupuestoB = Presupuesto::factory()->for($user)->create();
    $user->update(['presupuesto_activo_id' => $presupuestoB->id]);
    Cuenta::factory()->for($presupuestoA, 'presupuesto')->create(['nombre' => 'Efectivo']);

    $response = $this->actingAs($user->fresh())->post(route('cuentas.store'), [
        'nombre' => 'Efectivo',
        'tipo' => 'efectivo',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertRedirect(route('cuentas.index'));
    $this->assertDatabaseCount('cuentas', 2);
});

test('saldo_inicial_centavos igual a cero es valido', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'Nueva sin fondos',
        'tipo' => 'efectivo',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 0,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertRedirect(route('cuentas.index'));
    $this->assertDatabaseHas('cuentas', ['nombre' => 'Nueva sin fondos', 'saldo_inicial_centavos' => 0]);
});

test('numero_referencia nullable acepta null y string', function () {
    [$user] = usuarioConPresupuestoActivo();

    $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'Sin referencia',
        'tipo' => 'banco',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
        'numero_referencia' => null,
    ])->assertRedirect(route('cuentas.index'));

    $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'Con referencia',
        'tipo' => 'banco',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
        'numero_referencia' => '**1234',
    ])->assertRedirect(route('cuentas.index'));

    $this->assertDatabaseHas('cuentas', ['nombre' => 'Sin referencia', 'numero_referencia' => null]);
    $this->assertDatabaseHas('cuentas', ['nombre' => 'Con referencia', 'numero_referencia' => '**1234']);
});

// ---------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------

test('update permite editar nombre tipo saldo fecha y referencia', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $cuenta = Cuenta::factory()->for($activo, 'presupuesto')->banco()->create([
        'nombre' => 'Original',
        'saldo_inicial_centavos' => 100,
        'fecha_apertura' => '2026-01-01',
        'numero_referencia' => null,
    ]);

    $response = $this->actingAs($user)->put(route('cuentas.update', $cuenta), [
        'nombre' => 'Actualizada',
        'tipo' => 'efectivo',
        'saldo_inicial_centavos' => 999,
        'fecha_apertura' => '2026-02-01',
        'numero_referencia' => 'ref-1',
    ]);

    $response->assertRedirect(route('cuentas.index'));
    $cuenta->refresh();
    expect($cuenta->nombre)->toBe('Actualizada')
        ->and($cuenta->tipo)->toBe(TipoCuenta::Efectivo)
        ->and($cuenta->saldo_inicial_centavos)->toBe(999)
        ->and($cuenta->fecha_apertura->toDateString())->toBe('2026-02-01')
        ->and($cuenta->numero_referencia)->toBe('ref-1');
});

test('update ignora silenciosamente moneda_codigo', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $cuenta = Cuenta::factory()->for($activo, 'presupuesto')->create(['moneda_codigo' => 'BOB']);

    $this->actingAs($user)->put(route('cuentas.update', $cuenta), [
        'nombre' => $cuenta->nombre,
        'tipo' => $cuenta->tipo->value,
        'moneda_codigo' => 'USDT',
        'saldo_inicial_centavos' => $cuenta->saldo_inicial_centavos,
        'fecha_apertura' => $cuenta->fecha_apertura->toDateString(),
    ])->assertRedirect(route('cuentas.index'));

    expect($cuenta->fresh()->moneda_codigo)->toBe('BOB');
});

test('update ignora silenciosamente presupuesto_id', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $otroPresupuesto = Presupuesto::factory()->for($user)->create();
    $cuenta = Cuenta::factory()->for($activo, 'presupuesto')->create();

    $this->actingAs($user)->put(route('cuentas.update', $cuenta), [
        'nombre' => $cuenta->nombre,
        'tipo' => $cuenta->tipo->value,
        'presupuesto_id' => $otroPresupuesto->id,
        'saldo_inicial_centavos' => $cuenta->saldo_inicial_centavos,
        'fecha_apertura' => $cuenta->fecha_apertura->toDateString(),
    ])->assertRedirect(route('cuentas.index'));

    expect($cuenta->fresh()->presupuesto_id)->toBe($activo->id);
});

// ---------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------

test('destroy hace soft delete', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $cuenta = Cuenta::factory()->for($activo, 'presupuesto')->create();

    $response = $this->actingAs($user)->delete(route('cuentas.destroy', $cuenta));

    $response->assertRedirect(route('cuentas.index'));
    $this->assertSoftDeleted('cuentas', ['id' => $cuenta->id]);
});

// ---------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------

test('un usuario no puede editar una cuenta ajena', function () {
    $dueno = User::factory()->create();
    $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
    $cuenta = Cuenta::factory()->for($presupuestoDueno, 'presupuesto')->create();

    [$otro] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($otro)->put(route('cuentas.update', $cuenta), [
        'nombre' => 'Hackeada',
        'tipo' => 'banco',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('cuentas', ['id' => $cuenta->id, 'nombre' => $cuenta->nombre]);
});

test('un usuario no puede eliminar una cuenta ajena', function () {
    $dueno = User::factory()->create();
    $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
    $cuenta = Cuenta::factory()->for($presupuestoDueno, 'presupuesto')->create();

    $otro = User::factory()->create();

    $response = $this->actingAs($otro)->delete(route('cuentas.destroy', $cuenta));

    $response->assertForbidden();
    $this->assertDatabaseHas('cuentas', ['id' => $cuenta->id, 'deleted_at' => null]);
});

// ---------------------------------------------------------------------
// Modelo
// ---------------------------------------------------------------------

test('accessor saldo_actual_centavos retorna saldo_inicial_centavos en este change', function () {
    $cuenta = Cuenta::factory()->create(['saldo_inicial_centavos' => 200000]);

    expect($cuenta->saldo_actual_centavos)->toBe(200000);
});

// ---------------------------------------------------------------------
// Cobertura extra
// ---------------------------------------------------------------------

test('eliminar un presupuesto elimina en cascada sus cuentas', function () {
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create();
    $cuenta = Cuenta::factory()->for($presupuesto, 'presupuesto')->create();

    // forceDelete (hard delete real) dispara el cascadeOnDelete de la FK a
    // nivel de motor. Un delete() normal (soft delete de Presupuesto) es un
    // UPDATE de deleted_at, no un DELETE real, y no dispara la FK.
    $presupuesto->forceDelete();

    $this->assertDatabaseMissing('cuentas', ['id' => $cuenta->id]);
});

test('no se puede eliminar una moneda con cuentas asociadas', function () {
    $moneda = Moneda::factory()->create();
    Cuenta::factory()->create(['moneda_codigo' => $moneda->codigo]);

    expect(fn () => $moneda->delete())->toThrow(\Illuminate\Database\QueryException::class);
    $this->assertDatabaseHas('monedas', ['codigo' => $moneda->codigo]);
});

test('nombre de exactamente 100 caracteres es valido', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => str_repeat('a', 100),
        'tipo' => 'banco',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertRedirect(route('cuentas.index'));
    $this->assertDatabaseCount('cuentas', 1);
});

test('nombre de 101 caracteres falla la validacion', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => str_repeat('a', 101),
        'tipo' => 'banco',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => 1,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('nombre');
    $this->assertDatabaseCount('cuentas', 0);
});

test('saldo_inicial_centavos negativo falla la validacion', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('cuentas.store'), [
        'nombre' => 'X',
        'tipo' => 'banco',
        'moneda_codigo' => 'BOB',
        'saldo_inicial_centavos' => -1,
        'fecha_apertura' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('saldo_inicial_centavos');
    $this->assertDatabaseCount('cuentas', 0);
});
