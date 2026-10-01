<?php

use App\Models\Categoria;
use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Reutiliza el helper global ya declarado en CuentaTest.php (ver nota en
// GrupoCategoriaTest.php sobre por qué no se redeclara aquí).

test('store crea una categoria dentro del grupo', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();

    $response = $this->actingAs($user)->post(route('categorias.store'), [
        'grupo_categoria_id' => $grupo->id,
        'nombre' => 'Alquiler',
    ]);

    $response->assertRedirect(route('grupos-categorias.index'));
    $this->assertDatabaseHas('categorias', [
        'grupo_categoria_id' => $grupo->id,
        'nombre' => 'Alquiler',
    ]);
});

test('store rechaza nombre duplicado en el mismo grupo', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();
    $grupo->categorias()->create(['nombre' => 'Alquiler']);

    $response = $this->actingAs($user)->post(route('categorias.store'), [
        'grupo_categoria_id' => $grupo->id,
        'nombre' => 'ALQUILER',
    ]);

    $response->assertSessionHasErrors([
        'nombre' => 'Ya existe una categoría con ese nombre en este grupo.',
    ]);
    $this->assertDatabaseCount('categorias', 1);
});

test('store permite mismo nombre de categoria en otro grupo del mismo presupuesto', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo1 = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();
    $grupo2 = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();
    $grupo1->categorias()->create(['nombre' => 'Emergencia']);

    $response = $this->actingAs($user)->post(route('categorias.store'), [
        'grupo_categoria_id' => $grupo2->id,
        'nombre' => 'Emergencia',
    ]);

    $response->assertRedirect(route('grupos-categorias.index'));
    $this->assertDatabaseCount('categorias', 2);
});

test('store rechaza grupo_categoria_id que no pertenece al presupuesto activo del usuario', function () {
    [$user] = usuarioConPresupuestoActivo();
    $otro = User::factory()->create();
    $presupuestoOtro = Presupuesto::factory()->for($otro)->create();
    $grupoAjeno = GrupoCategoria::factory()->for($presupuestoOtro, 'presupuesto')->create();

    $response = $this->actingAs($user)->post(route('categorias.store'), [
        'grupo_categoria_id' => $grupoAjeno->id,
        'nombre' => 'Intento IDOR',
    ]);

    $response->assertSessionHasErrors([
        'grupo_categoria_id' => 'El grupo seleccionado no existe o no pertenece a tu presupuesto activo.',
    ]);
    $this->assertDatabaseCount('categorias', 0);
});

test('update permite editar el nombre de la categoria', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();
    $categoria = $grupo->categorias()->create(['nombre' => 'Original']);

    $response = $this->actingAs($user)->put(route('categorias.update', $categoria), [
        'nombre' => 'Actualizado',
    ]);

    $response->assertRedirect(route('grupos-categorias.index'));
    expect($categoria->fresh()->nombre)->toBe('Actualizado');
});

test('destroy hace soft delete de la categoria', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();
    $categoria = $grupo->categorias()->create(['nombre' => 'Test']);

    $response = $this->actingAs($user)->delete(route('categorias.destroy', $categoria));

    $response->assertRedirect(route('grupos-categorias.index'));
    $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
});

test('un usuario no puede editar una categoria ajena', function () {
    $dueno = User::factory()->create();
    $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
    $grupo = GrupoCategoria::factory()->for($presupuestoDueno, 'presupuesto')->create();
    $categoria = $grupo->categorias()->create(['nombre' => 'Ajena']);

    [$otro] = usuarioConPresupuestoActivo();

    $response = $this->actingAs($otro)->put(route('categorias.update', $categoria), [
        'nombre' => 'Hackeada',
    ]);

    $response->assertForbidden();
});

test('un usuario no puede eliminar una categoria ajena', function () {
    $dueno = User::factory()->create();
    $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
    $grupo = GrupoCategoria::factory()->for($presupuestoDueno, 'presupuesto')->create();
    $categoria = $grupo->categorias()->create(['nombre' => 'Ajena']);

    $otro = User::factory()->create();

    $response = $this->actingAs($otro)->delete(route('categorias.destroy', $categoria));

    $response->assertForbidden();
    $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'deleted_at' => null]);
});

// ---------------------------------------------------------------------
// Extras
// ---------------------------------------------------------------------

test('nombre de categoria con exactamente 100 caracteres es valido', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();

    $response = $this->actingAs($user)->post(route('categorias.store'), [
        'grupo_categoria_id' => $grupo->id,
        'nombre' => str_repeat('a', 100),
    ]);

    $response->assertRedirect(route('grupos-categorias.index'));
    $this->assertDatabaseCount('categorias', 1);
});

test('nombre de categoria con 101 caracteres falla', function () {
    [$user, $activo] = usuarioConPresupuestoActivo();
    $grupo = GrupoCategoria::factory()->for($activo, 'presupuesto')->create();

    $response = $this->actingAs($user)->post(route('categorias.store'), [
        'grupo_categoria_id' => $grupo->id,
        'nombre' => str_repeat('a', 101),
    ]);

    $response->assertSessionHasErrors([
        'nombre' => 'El nombre no puede tener más de 100 caracteres.',
    ]);
    $this->assertDatabaseCount('categorias', 0);
});

test('policy de categoria resuelve los 2 saltos sin relacion cargada previamente', function () {
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create();
    $grupo = GrupoCategoria::factory()->for($presupuesto, 'presupuesto')->create();
    $categoria = $grupo->categorias()->create(['nombre' => 'Test']);

    // Instancia fresca, sin ninguna relación precargada: fuerza a la policy
    // a resolver grupoCategoria->presupuesto por lazy load si hiciera falta.
    $categoriaFresca = Categoria::find($categoria->id);

    expect($categoriaFresca->relationLoaded('grupoCategoria'))->toBeFalse();
    expect($user->can('update', $categoriaFresca))->toBeTrue();
});
