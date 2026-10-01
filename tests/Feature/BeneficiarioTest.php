<?php

use App\Models\Beneficiario;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Reutiliza el helper global ya declarado en CuentaTest.php (Change 4) — Pest
// carga todos los archivos de test en el mismo proceso/namespace global antes
// de ejecutar nada, así que una función de nivel superior definida una sola
// vez en la suite está disponible en cualquier otro archivo. No se redeclara
// aquí (mismo patrón que GrupoCategoriaTest/CategoriaTest, Change 5).

// ---------------------------------------------------------------------
// Acceso
// ---------------------------------------------------------------------

test('usuario ve solo beneficiarios de su presupuesto activo', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $otroPresupuesto = Presupuesto::factory()->for($user)->create();

    Beneficiario::factory()->for($activo, 'presupuesto')->count(3)->create();
    Beneficiario::factory()->for($otroPresupuesto, 'presupuesto')->create();

    $response = $this->actingAs($user)->get(route('beneficiarios.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Beneficiarios/Index')
        ->has('beneficiarios', 3)
    );
});

test('sin presupuesto activo redirige a dashboard con flash', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('beneficiarios.index'));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('flash.danger', 'Necesitas un presupuesto activo para gestionar beneficiarios.');
});

test('usuario no autenticado no accede a beneficiarios', function () {
    $response = $this->get(route('beneficiarios.index'));

    $response->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------

test('store crea un beneficiario valido asignado al presupuesto activo', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => 'Netflix',
        'notas' => 'Suscripción mensual',
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    $this->assertDatabaseHas('beneficiarios', [
        'presupuesto_id' => $activo->id,
        'nombre' => 'Netflix',
        'notas' => 'Suscripción mensual',
    ]);
});

test('store rechaza nombre duplicado case-insensitive en el mismo presupuesto', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    Beneficiario::factory()->for($activo, 'presupuesto')->create(['nombre' => 'Netflix']);

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => 'NETFLIX',
    ]);

    $response->assertSessionHasErrors([
        'nombre' => 'Ya existe un beneficiario con ese nombre en este presupuesto.',
    ]);
    $this->assertDatabaseCount('beneficiarios', 1);
});

test('store permite mismo nombre en otro presupuesto del mismo usuario', function () {
    $user = User::factory()->create();
    $presupuestoA = Presupuesto::factory()->for($user)->create();
    $presupuestoB = Presupuesto::factory()->for($user)->create();
    $user->update(['presupuesto_activo_id' => $presupuestoB->id]);
    Beneficiario::factory()->for($presupuestoA, 'presupuesto')->create(['nombre' => 'Netflix']);

    $response = $this->actingAs($user->fresh())->post(route('beneficiarios.store'), [
        'nombre' => 'Netflix',
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    $this->assertDatabaseCount('beneficiarios', 2);
});

test('store rechaza nombre vacio', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => '',
    ]);

    $response->assertSessionHasErrors([
        'nombre' => 'El nombre es requerido.',
    ]);
    $this->assertDatabaseCount('beneficiarios', 0);
});

test('store rechaza nombre con mas de 100 caracteres', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => str_repeat('a', 101),
    ]);

    $response->assertSessionHasErrors([
        'nombre' => 'El nombre no puede tener más de 100 caracteres.',
    ]);
    $this->assertDatabaseCount('beneficiarios', 0);
});

test('store acepta notas null', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => 'Sin Notas',
        'notas' => null,
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    $this->assertDatabaseHas('beneficiarios', ['nombre' => 'Sin Notas', 'notas' => null]);
});

test('store acepta notas string valido', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => 'Con Notas',
        'notas' => 'Una nota de contexto',
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    $this->assertDatabaseHas('beneficiarios', ['nombre' => 'Con Notas', 'notas' => 'Una nota de contexto']);
});

test('store rechaza notas con mas de 1000 caracteres', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => 'Notas Largas',
        'notas' => str_repeat('a', 1001),
    ]);

    $response->assertSessionHasErrors([
        'notas' => 'Las notas no pueden tener más de 1000 caracteres.',
    ]);
    $this->assertDatabaseCount('beneficiarios', 0);
});

// ---------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------

test('update permite editar nombre y notas', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $beneficiario = Beneficiario::factory()->for($activo, 'presupuesto')->create([
        'nombre' => 'Original', 'notas' => null,
    ]);

    $response = $this->actingAs($user)->put(route('beneficiarios.update', $beneficiario), [
        'nombre' => 'Actualizado',
        'notas' => 'Nota nueva',
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    $beneficiario->refresh();
    expect($beneficiario->nombre)->toBe('Actualizado')
        ->and($beneficiario->notas)->toBe('Nota nueva');
});

test('update ignora presupuesto_id manipulado en el payload', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $otroPresupuesto = Presupuesto::factory()->for($user)->create();
    $beneficiario = Beneficiario::factory()->for($activo, 'presupuesto')->create();

    $response = $this->actingAs($user)->put(route('beneficiarios.update', $beneficiario), [
        'nombre' => 'Actualizado',
        'presupuesto_id' => $otroPresupuesto->id,
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    expect($beneficiario->fresh()->presupuesto_id)->toBe($activo->id);
});

// ---------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------

test('destroy hace soft delete del beneficiario', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $beneficiario = Beneficiario::factory()->for($activo, 'presupuesto')->create();

    $response = $this->actingAs($user)->delete(route('beneficiarios.destroy', $beneficiario));

    $response->assertRedirect(route('beneficiarios.index'));
    $this->assertSoftDeleted('beneficiarios', ['id' => $beneficiario->id]);
});

// ---------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------

test('un usuario no puede editar un beneficiario ajeno', function () {
    $dueno = User::factory()->create();
    $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
    $beneficiario = Beneficiario::factory()->for($presupuestoDueno, 'presupuesto')->create();

    [$otro] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($otro)->put(route('beneficiarios.update', $beneficiario), [
        'nombre' => 'Hackeado',
    ]);

    $response->assertForbidden();
});

test('un usuario no puede eliminar un beneficiario ajeno', function () {
    $dueno = User::factory()->create();
    $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
    $beneficiario = Beneficiario::factory()->for($presupuestoDueno, 'presupuesto')->create();

    $otro = User::factory()->create();

    $response = $this->actingAs($otro)->delete(route('beneficiarios.destroy', $beneficiario));

    $response->assertForbidden();
    $this->assertDatabaseHas('beneficiarios', ['id' => $beneficiario->id, 'deleted_at' => null]);
});

// ---------------------------------------------------------------------
// Cascada
// ---------------------------------------------------------------------

test('forceDelete de presupuesto elimina en cascada sus beneficiarios via FK', function () {
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create();
    $beneficiario = Beneficiario::factory()->for($presupuesto, 'presupuesto')->create();

    // forceDelete (hard delete real) dispara el cascadeOnDelete de la FK a
    // nivel de motor. Un delete() normal (soft delete de Presupuesto) es un
    // UPDATE de deleted_at, no un DELETE real, y no la dispara — mismo
    // precedente que CuentaTest (Change 4) y GrupoCategoriaTest (Change 5).
    $presupuesto->forceDelete();

    $this->assertDatabaseMissing('beneficiarios', ['id' => $beneficiario->id]);
});

// ---------------------------------------------------------------------
// Extras
// ---------------------------------------------------------------------

test('nombre con exactamente 100 caracteres es valido', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => str_repeat('a', 100),
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    $this->assertDatabaseCount('beneficiarios', 1);
});

test('notas con exactamente 1000 caracteres es valido', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => 'Notas Al Limite',
        'notas' => str_repeat('a', 1000),
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    $this->assertDatabaseHas('beneficiarios', ['nombre' => 'Notas Al Limite']);
});

test('store con presupuesto_id manipulado en el payload es ignorado', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $otroPresupuesto = Presupuesto::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('beneficiarios.store'), [
        'nombre' => 'Intento IDOR',
        'presupuesto_id' => $otroPresupuesto->id,
    ]);

    $response->assertRedirect(route('beneficiarios.index'));
    $this->assertDatabaseHas('beneficiarios', [
        'nombre' => 'Intento IDOR',
        'presupuesto_id' => $activo->id,
    ]);
});

test('policy de beneficiario resuelve con lazy load sin relacion precargada', function () {
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create();
    $beneficiario = Beneficiario::factory()->for($presupuesto, 'presupuesto')->create();

    // Instancia fresca sin ninguna relacion cargada: fuerza a la policy a
    // resolver presupuesto por lazy load si hiciera falta.
    $beneficiarioFresco = Beneficiario::find($beneficiario->id);

    expect($beneficiarioFresco->relationLoaded('presupuesto'))->toBeFalse();
    expect($user->can('update', $beneficiarioFresco))->toBeTrue();
});

test('beneficiario soft-deleted no aparece en el index', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $beneficiario = Beneficiario::factory()->for($activo, 'presupuesto')->create();
    $beneficiario->delete();

    $response = $this->actingAs($user)->get(route('beneficiarios.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Beneficiarios/Index')
        ->has('beneficiarios', 0)
    );
});
