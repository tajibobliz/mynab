<?php

use App\Models\Categoria;
use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Reutiliza el helper global ya declarado en CuentaTest.php (Change 4) — Pest
// carga todos los archivos de test en el mismo proceso/namespace global antes
// de ejecutar nada, así que una función de nivel superior definida una sola
// vez en la suite está disponible en cualquier otro archivo. No se redeclara
// aquí para evitar un fatal "Cannot redeclare function".

// ---------------------------------------------------------------------
// Grupos (grupos_categorias)
// ---------------------------------------------------------------------

test('usuario ve solo grupos de su presupuesto activo', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $otroPresupuesto = Presupuesto::factory()->for($user)->create();

    GrupoCategoria::factory()->for($activo, 'presupuesto')->count(2)->create();
    GrupoCategoria::factory()->for($otroPresupuesto, 'presupuesto')->create();

    $response = $this->actingAs($user)->get(route('grupos-categorias.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('GruposCategorias/Index')
        ->has('grupos', 2)
    );
});

test('store crea un grupo valido', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('grupos-categorias.store'), [
        'nombre' => 'Obligaciones inmediatas',
        'color' => '#ef4444',
        'icono' => 'AlertCircle',
    ]);

    $response->assertRedirect(route('grupos-categorias.index'));
    $this->assertDatabaseHas('grupos_categorias', [
        'presupuesto_id' => $activo->id,
        'nombre' => 'Obligaciones inmediatas',
        'color' => '#ef4444',
        'icono' => 'AlertCircle',
    ]);
});

test('store rechaza nombre duplicado en el mismo presupuesto', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    GrupoCategoria::factory()->for($activo, 'presupuesto')->create(['nombre' => 'Ahorros']);

    $response = $this->actingAs($user)->post(route('grupos-categorias.store'), [
        'nombre' => 'AHORROS',
        'color' => '#22c55e',
        'icono' => 'PiggyBank',
    ]);

    $response->assertSessionHasErrors([
        'nombre' => 'Ya existe un grupo con ese nombre en este presupuesto.',
    ]);
    $this->assertDatabaseCount('grupos_categorias', 1);
});

test('store permite mismo nombre de grupo en otro presupuesto', function () {
    $user = User::factory()->create();
    $presupuestoA = Presupuesto::factory()->for($user)->create();
    $presupuestoB = Presupuesto::factory()->for($user)->create();
    $user->update(['presupuesto_activo_id' => $presupuestoB->id]);
    GrupoCategoria::factory()->for($presupuestoA, 'presupuesto')->create(['nombre' => 'Ahorros']);

    $response = $this->actingAs($user->fresh())->post(route('grupos-categorias.store'), [
        'nombre' => 'Ahorros',
        'color' => '#22c55e',
        'icono' => 'PiggyBank',
    ]);

    $response->assertRedirect(route('grupos-categorias.index'));
    $this->assertDatabaseCount('grupos_categorias', 2);
});

test('store rechaza color no hex', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('grupos-categorias.store'), [
        'nombre' => 'X',
        'color' => 'red',
        'icono' => 'AlertCircle',
    ]);

    $response->assertSessionHasErrors([
        'color' => 'El color debe ser un hexadecimal válido (#RRGGBB).',
    ]);
    $this->assertDatabaseCount('grupos_categorias', 0);
});

test('store rechaza icono fuera de whitelist', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('grupos-categorias.store'), [
        'nombre' => 'X',
        'color' => '#ef4444',
        'icono' => 'Rocket',
    ]);

    $response->assertSessionHasErrors([
        'icono' => 'Selecciona un icono válido de la lista.',
    ]);
    $this->assertDatabaseCount('grupos_categorias', 0);
});

test('update permite editar nombre color e icono', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo = GrupoCategoria::factory()->for($activo, 'presupuesto')->create([
        'nombre' => 'Original', 'color' => '#ef4444', 'icono' => 'AlertCircle',
    ]);

    $response = $this->actingAs($user)->put(route('grupos-categorias.update', $grupo), [
        'nombre' => 'Actualizado',
        'color' => '#22c55e',
        'icono' => 'PiggyBank',
    ]);

    $response->assertRedirect(route('grupos-categorias.index'));
    $grupo->refresh();
    expect($grupo->nombre)->toBe('Actualizado')
        ->and($grupo->color)->toBe('#22c55e')
        ->and($grupo->icono)->toBe('PiggyBank');
});

test('destroy hace soft delete del grupo y sus categorias en cascada', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();
    $cat1 = $grupo->categorias()->create(['nombre' => 'Cat 1']);
    $cat2 = $grupo->categorias()->create(['nombre' => 'Cat 2']);

    $response = $this->actingAs($user)->delete(route('grupos-categorias.destroy', $grupo));

    $response->assertRedirect(route('grupos-categorias.index'));
    $this->assertSoftDeleted('grupos_categorias', ['id' => $grupo->id]);
    $this->assertSoftDeleted('categorias', ['id' => $cat1->id]);
    $this->assertSoftDeleted('categorias', ['id' => $cat2->id]);
});

test('un usuario no puede editar un grupo ajeno', function () {
    $dueno = User::factory()->create();
    $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
    $grupo = GrupoCategoria::factory()->for($presupuestoDueno, 'presupuesto')->create();

    [$otro] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($otro)->put(route('grupos-categorias.update', $grupo), [
        'nombre' => 'Hackeado',
        'color' => '#000000',
        'icono' => 'AlertCircle',
    ]);

    $response->assertForbidden();
});

test('un usuario no puede eliminar un grupo ajeno', function () {
    $dueno = User::factory()->create();
    $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
    $grupo = GrupoCategoria::factory()->for($presupuestoDueno, 'presupuesto')->create();

    $otro = User::factory()->create();

    $response = $this->actingAs($otro)->delete(route('grupos-categorias.destroy', $grupo));

    $response->assertForbidden();
    $this->assertDatabaseHas('grupos_categorias', ['id' => $grupo->id, 'deleted_at' => null]);
});

// ---------------------------------------------------------------------
// Extras
// ---------------------------------------------------------------------

test('nombre de grupo con exactamente 100 caracteres es valido', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('grupos-categorias.store'), [
        'nombre' => str_repeat('a', 100),
        'color' => '#ef4444',
        'icono' => 'AlertCircle',
    ]);

    $response->assertRedirect(route('grupos-categorias.index'));
    $this->assertDatabaseCount('grupos_categorias', 1);
});

test('nombre de grupo con 101 caracteres falla', function () {
    [$user] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($user)->post(route('grupos-categorias.store'), [
        'nombre' => str_repeat('a', 101),
        'color' => '#ef4444',
        'icono' => 'AlertCircle',
    ]);

    $response->assertSessionHasErrors([
        'nombre' => 'El nombre no puede tener más de 100 caracteres.',
    ]);
    $this->assertDatabaseCount('grupos_categorias', 0);
});

test('el hook deleting cascadea tambien al usar Model::destroy en lote', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo1 = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();
    $cat1 = $grupo1->categorias()->create(['nombre' => 'Cat A']);
    $grupo2 = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();
    $cat2 = $grupo2->categorias()->create(['nombre' => 'Cat B']);

    GrupoCategoria::destroy([$grupo1->id, $grupo2->id]);

    $this->assertSoftDeleted('grupos_categorias', ['id' => $grupo1->id]);
    $this->assertSoftDeleted('grupos_categorias', ['id' => $grupo2->id]);
    $this->assertSoftDeleted('categorias', ['id' => $cat1->id]);
    $this->assertSoftDeleted('categorias', ['id' => $cat2->id]);
});

test('sin presupuesto activo grupos-categorias redirige a dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('grupos-categorias.index'));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('flash.danger');
});

test('un usuario no autenticado no accede a grupos-categorias', function () {
    $response = $this->get(route('grupos-categorias.index'));

    $response->assertRedirect(route('login'));
});

test('forceDelete de presupuesto elimina en cascada sus grupos y categorias via FK', function () {
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create();
    $grupo = GrupoCategoria::factory()->for($presupuesto, 'presupuesto')->create();
    $categoria = $grupo->categorias()->create(['nombre' => 'Test']);

    // forceDelete (hard delete real) dispara el cascadeOnDelete de la FK a
    // nivel de motor. Un delete() normal (soft delete de Presupuesto) es un
    // UPDATE de deleted_at, no un DELETE real, y no la dispara — mismo
    // precedente que CuentaTest (Change 4).
    $presupuesto->forceDelete();

    $this->assertDatabaseMissing('grupos_categorias', ['id' => $grupo->id]);
    $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
});
