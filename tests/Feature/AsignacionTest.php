<?php

use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usuarioConAsignacion(): array
{
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create(['moneda_base_codigo' => 'BOB']);
    $user->update(['presupuesto_activo_id' => $presupuesto->id]);
    $grupo = GrupoCategoria::factory()->for($presupuesto, 'presupuesto')->create();
    $categoria = Categoria::factory()->for($grupo, 'grupoCategoria')->create();

    return [$user->fresh(), $presupuesto, $categoria];
}

// ---------------------------------------------------------------------
// 12.6: store crea / 12.7: store repetido actualiza
// ---------------------------------------------------------------------

test('store crea una asignación nueva', function () {
    [$user, $presupuesto, $categoria] = usuarioConAsignacion();

    $response = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2026, 'mes' => 10, 'monto_centavos' => 150000,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('asignaciones', [
        'presupuesto_id' => $presupuesto->id,
        'categoria_id' => $categoria->id,
        'año' => 2026,
        'mes' => 10,
        'monto_centavos' => 150000,
    ]);
    expect(Asignacion::count())->toBe(1);
});

test('store repetido con mismo presupuesto/categoria/año/mes actualiza en vez de duplicar', function () {
    [$user, , $categoria] = usuarioConAsignacion();

    $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2026, 'mes' => 10, 'monto_centavos' => 150000,
    ]);
    $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2026, 'mes' => 10, 'monto_centavos' => 200000,
    ]);

    expect(Asignacion::count())->toBe(1);
    $this->assertDatabaseHas('asignaciones', ['categoria_id' => $categoria->id, 'monto_centavos' => 200000]);
});

test('store con monto_centavos = 0 es válido (sin asignar)', function () {
    [$user, , $categoria] = usuarioConAsignacion();

    $response = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2026, 'mes' => 10, 'monto_centavos' => 0,
    ]);

    $response->assertRedirect()->assertSessionDoesntHaveErrors();
    $this->assertDatabaseHas('asignaciones', ['categoria_id' => $categoria->id, 'monto_centavos' => 0]);
});

// ---------------------------------------------------------------------
// 12.8 + extra: IDOR en categoria_id (otro presupuesto del mismo usuario,
// y categoría de otro usuario por completo)
// ---------------------------------------------------------------------

test('store con categoria de otro presupuesto del mismo usuario falla (IDOR)', function () {
    [$user] = usuarioConAsignacion();
    $otroPresupuesto = Presupuesto::factory()->for($user)->create();
    $otroGrupo = GrupoCategoria::factory()->for($otroPresupuesto, 'presupuesto')->create();
    $categoriaAjena = Categoria::factory()->for($otroGrupo, 'grupoCategoria')->create();

    $response = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoriaAjena->id, 'año' => 2026, 'mes' => 10, 'monto_centavos' => 150000,
    ]);

    $response->assertSessionHasErrors(['categoria_id' => 'La categoría no existe en este presupuesto.']);
    expect(Asignacion::count())->toBe(0);
});

test('store con categoria de otro usuario por completo falla (IDOR)', function () {
    [$user] = usuarioConAsignacion();
    [, , $categoriaDeOtroUsuario] = usuarioConAsignacion();

    $response = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoriaDeOtroUsuario->id, 'año' => 2026, 'mes' => 10, 'monto_centavos' => 1000,
    ]);

    $response->assertSessionHasErrors(['categoria_id' => 'La categoría no existe en este presupuesto.']);
    expect(Asignacion::count())->toBe(0);
});

// ---------------------------------------------------------------------
// 12.9 + extras: validaciones con mensajes exactos (leídos de messages(),
// no hardcodeados por separado)
// ---------------------------------------------------------------------

test('store con monto_centavos negativo falla con mensaje exacto', function () {
    [$user, , $categoria] = usuarioConAsignacion();

    $mensajeEsperado = (new App\Http\Requests\StoreAsignacionRequest())->messages()['monto_centavos.min'];

    $response = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2026, 'mes' => 10, 'monto_centavos' => -100,
    ]);

    $response->assertSessionHasErrors(['monto_centavos' => $mensajeEsperado]);
    expect(Asignacion::count())->toBe(0);
});

test('store con año fuera de rango falla con mensaje exacto', function () {
    [$user, , $categoria] = usuarioConAsignacion();

    $mensajeEsperado = (new App\Http\Requests\StoreAsignacionRequest())->messages()['año.between'];

    $responseBajo = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2019, 'mes' => 10, 'monto_centavos' => 1000,
    ]);
    $responseAlto = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2101, 'mes' => 10, 'monto_centavos' => 1000,
    ]);

    $responseBajo->assertSessionHasErrors(['año' => $mensajeEsperado]);
    $responseAlto->assertSessionHasErrors(['año' => $mensajeEsperado]);
});

test('store con mes fuera de rango (0 o 13) falla con mensaje exacto', function () {
    [$user, , $categoria] = usuarioConAsignacion();

    $mensajeEsperado = (new App\Http\Requests\StoreAsignacionRequest())->messages()['mes.between'];

    $responseCero = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2026, 'mes' => 0, 'monto_centavos' => 1000,
    ]);
    $responseTrece = $this->actingAs($user)->post(route('asignaciones.store'), [
        'categoria_id' => $categoria->id, 'año' => 2026, 'mes' => 13, 'monto_centavos' => 1000,
    ]);

    $responseCero->assertSessionHasErrors(['mes' => $mensajeEsperado]);
    $responseTrece->assertSessionHasErrors(['mes' => $mensajeEsperado]);
});

// ---------------------------------------------------------------------
// 12.10: policy — sin ruta HTTP de update/destroy para Asignacion (solo
// store/upsert, que por construcción nunca puede tocar la asignación de
// otro usuario porque presupuesto_id siempre sale del presupuesto activo
// del propio request). Se verifica la Policy directamente, mismo patrón
// que TransaccionSplitPolicy (Change 7): definida y correcta aunque
// ninguna ruta la ejercite todavía.
// ---------------------------------------------------------------------

test('policy: usuario no puede ver, actualizar ni eliminar una asignación ajena', function () {
    [$dueño, $presupuesto, $categoria] = usuarioConAsignacion();
    $asignacion = Asignacion::factory()->for($presupuesto, 'presupuesto')->for($categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10,
    ]);

    $otroUsuario = User::factory()->create();

    expect($dueño->can('view', $asignacion))->toBeTrue()
        ->and($dueño->can('update', $asignacion))->toBeTrue()
        ->and($dueño->can('delete', $asignacion))->toBeTrue()
        ->and($otroUsuario->can('view', $asignacion))->toBeFalse()
        ->and($otroUsuario->can('update', $asignacion))->toBeFalse()
        ->and($otroUsuario->can('delete', $asignacion))->toBeFalse();
});

// ---------------------------------------------------------------------
// Nota: un test de "POST /asignaciones sin sesión -> 419 (CSRF)" se
// consideró y se descartó para Pest: Laravel desactiva la verificación de
// CSRF por defecto en el entorno de test (confirmado empíricamente — un
// POST sin token ahí da 302, nunca 419), así que un test así no
// reproduciría el comportamiento real. Ya se verificó con curl real contra
// el servidor dev en el Grupo 7 (419 confirmado).
// ---------------------------------------------------------------------
