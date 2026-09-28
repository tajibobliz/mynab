<?php

use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------
// Listado
// ---------------------------------------------------------------------

test('usuario autenticado ve su lista de presupuestos', function () {
    $user = User::factory()->create();
    Presupuesto::factory()->for($user)->count(2)->create();

    $otro = User::factory()->create();
    Presupuesto::factory()->for($otro)->create();

    $response = $this->actingAs($user)->get(route('presupuestos.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Presupuestos/Index')
        ->has('presupuestos', 2)
    );
});

test('un usuario no autenticado no puede acceder a /presupuestos', function () {
    $response = $this->get(route('presupuestos.index'));

    $response->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------
// Creación
// ---------------------------------------------------------------------

test('usuario autenticado puede crear un presupuesto con datos válidos', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'Personal',
        'descripcion' => 'Gastos del día a día',
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);

    $response->assertRedirect(route('presupuestos.index'));
    $this->assertDatabaseHas('presupuestos', [
        'user_id' => $user->id,
        'nombre' => 'Personal',
        'descripcion' => 'Gastos del día a día',
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);
});

test('crear presupuesto con nombre duplicado case-insensitive para el mismo usuario falla', function () {
    $user = User::factory()->create();
    Presupuesto::factory()->for($user)->create(['nombre' => 'Personal']);

    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'PERSONAL',
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);

    $response->assertSessionHasErrors('nombre');
    $this->assertDatabaseCount('presupuestos', 1);
});

test('crear presupuesto con el mismo nombre para otro usuario es permitido', function () {
    $otro = User::factory()->create();
    Presupuesto::factory()->for($otro)->create(['nombre' => 'Personal']);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'Personal',
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);

    $response->assertRedirect(route('presupuestos.index'));
    $this->assertDatabaseHas('presupuestos', ['user_id' => $user->id, 'nombre' => 'Personal']);
    $this->assertDatabaseCount('presupuestos', 2);
});

test('el primer presupuesto creado se asigna automáticamente como activo', function () {
    $user = User::factory()->create();
    expect($user->presupuesto_activo_id)->toBeNull();

    $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'Uno',
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);

    $primero = Presupuesto::where('user_id', $user->id)->firstOrFail();
    expect($user->fresh()->presupuesto_activo_id)->toBe($primero->id);

    // Un segundo presupuesto NO debe pisar el activo ya asignado.
    $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'Dos',
        'color' => '#8b5cf6',
        'icono' => 'briefcase',
    ]);

    expect($user->fresh()->presupuesto_activo_id)->toBe($primero->id);
});

// ---------------------------------------------------------------------
// Edición
// ---------------------------------------------------------------------

test('usuario autenticado puede actualizar su propio presupuesto', function () {
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create(['nombre' => 'Personal']);

    $response = $this->actingAs($user)->put(route('presupuestos.update', $presupuesto), [
        'nombre' => 'Personal actualizado',
        'descripcion' => 'Nueva descripción',
        'color' => '#3b82f6',
        'icono' => 'home',
    ]);

    $response->assertRedirect(route('presupuestos.index'));
    $this->assertDatabaseHas('presupuestos', [
        'id' => $presupuesto->id,
        'nombre' => 'Personal actualizado',
        'color' => '#3b82f6',
        'icono' => 'home',
    ]);
});

test('usuario autenticado no puede actualizar presupuesto de otro usuario', function () {
    $dueno = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($dueno)->create(['nombre' => 'Personal']);

    $otro = User::factory()->create();
    $response = $this->actingAs($otro)->put(route('presupuestos.update', $presupuesto), [
        'nombre' => 'Hackeado',
        'color' => '#ef4444',
        'icono' => 'wallet',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('presupuestos', ['id' => $presupuesto->id, 'nombre' => 'Personal']);
});

// ---------------------------------------------------------------------
// Eliminación (soft delete) y reasignación de activo
// ---------------------------------------------------------------------

test('usuario autenticado puede eliminar (soft delete) su propio presupuesto', function () {
    $user = User::factory()->create();
    $activo = Presupuesto::factory()->for($user)->create();
    $user->update(['presupuesto_activo_id' => $activo->id]);
    $otro = Presupuesto::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete(route('presupuestos.destroy', $otro));

    $response->assertRedirect(route('presupuestos.index'));
    $this->assertSoftDeleted('presupuestos', ['id' => $otro->id]);
});

test('al eliminar el presupuesto activo, se reasigna al siguiente disponible (el más antiguo restante)', function () {
    $user = User::factory()->create();
    $primero = Presupuesto::factory()->for($user)->create(['nombre' => 'Uno', 'created_at' => now()->subDays(2)]);
    $segundo = Presupuesto::factory()->for($user)->create(['nombre' => 'Dos', 'created_at' => now()->subDay()]);
    $tercero = Presupuesto::factory()->for($user)->create(['nombre' => 'Tres', 'created_at' => now()]);
    $user->update(['presupuesto_activo_id' => $tercero->id]);

    $response = $this->actingAs($user)->delete(route('presupuestos.destroy', $tercero));

    $response->assertRedirect(route('presupuestos.index'));
    expect($user->fresh()->presupuesto_activo_id)->toBe($primero->id)
        ->and($segundo->id)->not->toBe($primero->id); // sanity check del fixture
});

test('al eliminar el último presupuesto, presupuesto_activo_id queda en null y redirige al dashboard', function () {
    $user = User::factory()->create();
    $unico = Presupuesto::factory()->for($user)->create();
    $user->update(['presupuesto_activo_id' => $unico->id]);

    $response = $this->actingAs($user)->delete(route('presupuestos.destroy', $unico));

    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->presupuesto_activo_id)->toBeNull();
    $this->assertSoftDeleted('presupuestos', ['id' => $unico->id]);
});

// ---------------------------------------------------------------------
// Selección de presupuesto activo
// ---------------------------------------------------------------------

test('usuario puede cambiar el presupuesto activo con POST /presupuestos/{id}/seleccionar', function () {
    $user = User::factory()->create();
    $uno = Presupuesto::factory()->for($user)->create();
    $dos = Presupuesto::factory()->for($user)->create();
    $user->update(['presupuesto_activo_id' => $uno->id]);

    $response = $this->actingAs($user)->post(route('presupuestos.seleccionar', $dos));

    $response->assertRedirect();
    expect($user->fresh()->presupuesto_activo_id)->toBe($dos->id);
});

test('usuario no puede seleccionar como activo un presupuesto que no es suyo', function () {
    $dueno = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($dueno)->create();

    $otro = User::factory()->create();
    $response = $this->actingAs($otro)->post(route('presupuestos.seleccionar', $presupuesto));

    $response->assertForbidden();
    expect($otro->fresh()->presupuesto_activo_id)->toBeNull();
});

// ---------------------------------------------------------------------
// Validación
// ---------------------------------------------------------------------

test('la validación rechaza un color con formato hex inválido', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'Personal',
        'color' => 'verde',
        'icono' => 'wallet',
    ]);

    $response->assertSessionHasErrors('color');
    $this->assertDatabaseCount('presupuestos', 0);
});

test('la validación rechaza un icono fuera de la whitelist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'Personal',
        'color' => '#22c55e',
        'icono' => 'icono-que-no-existe',
    ]);

    $response->assertSessionHasErrors('icono');
    $this->assertDatabaseCount('presupuestos', 0);
});

test('la validación rechaza un nombre vacío', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => '',
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);

    $response->assertSessionHasErrors('nombre');
});

test('la validación rechaza un nombre de menos de 2 caracteres', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'A',
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);

    $response->assertSessionHasErrors('nombre');
});

test('la validación rechaza un nombre de más de 100 caracteres', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => str_repeat('a', 101),
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);

    $response->assertSessionHasErrors('nombre');
});

test('la validación rechaza una descripción de más de 500 caracteres', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('presupuestos.store'), [
        'nombre' => 'Personal',
        'descripcion' => str_repeat('a', 501),
        'color' => '#22c55e',
        'icono' => 'wallet',
    ]);

    $response->assertSessionHasErrors('descripcion');
});
