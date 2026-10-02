<?php

use App\Models\Asignacion;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function usuarioConVistaMensual(): array
{
    $user = User::factory()->create();
    $presupuesto = Presupuesto::factory()->for($user)->create(['moneda_base_codigo' => 'BOB']);
    $user->update(['presupuesto_activo_id' => $presupuesto->id]);
    $cuenta = Cuenta::factory()->for($presupuesto, 'presupuesto')->create();
    $grupo = GrupoCategoria::factory()->for($presupuesto, 'presupuesto')->create();
    $categoria = Categoria::factory()->for($grupo, 'grupoCategoria')->create();

    return [$user->fresh(), $presupuesto, $cuenta, $categoria];
}

test('vista mensual con año y mes válidos muestra datos correctos', function () {
    [$user, $presupuesto, $cuenta, $categoria] = usuarioConVistaMensual();

    Asignacion::factory()->for($presupuesto, 'presupuesto')->for($categoria, 'categoria')->create([
        'año' => 2026, 'mes' => 10, 'monto_centavos' => 150000,
    ]);
    Transaccion::factory()->outflow()->for($cuenta, 'cuenta')->create([
        'categoria_id' => $categoria->id, 'monto_moneda_base_centavos' => 40000, 'fecha_hora' => '2026-10-10 12:00:00',
    ]);

    $response = $this->actingAs($user)->get(route('presupuesto-mensual.index', ['año' => 2026, 'mes' => 10]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('PresupuestoMensual/Index')
        ->where('año', 2026)
        ->where('mes', 10)
        ->where('vista.grupos.0.categorias.0.assigned', 150000)
        ->where('vista.grupos.0.categorias.0.activity', 40000)
        ->where('vista.grupos.0.categorias.0.available', 110000)
    );
});

test('vista mensual sin asignaciones del mes muestra Assigned en 0', function () {
    [$user, , , $categoria] = usuarioConVistaMensual();

    // Categoría existe pero nunca se le asignó nada en ningún mes — la
    // fila de asignaciones simplemente no existe, no es un registro con
    // monto 0 explícito.
    $response = $this->actingAs($user)->get(route('presupuesto-mensual.index', ['año' => 2026, 'mes' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('vista.grupos.0.categorias.0.id', $categoria->id)
        ->where('vista.grupos.0.categorias.0.assigned', 0)
        ->where('vista.grupos.0.categorias.0.activity', 0)
        ->where('vista.grupos.0.categorias.0.available', 0)
    );
});

test('vista mensual sin presupuesto activo redirige a dashboard', function () {
    $user = User::factory()->create(['presupuesto_activo_id' => null]);

    $response = $this->actingAs($user)->get(route('presupuesto-mensual.index', ['año' => 2026, 'mes' => 10]));

    $response->assertRedirect(route('dashboard'));
});

test('vista mensual sin query string usa el mes actual por defecto', function () {
    [$user] = usuarioConVistaMensual();

    $response = $this->actingAs($user)->get(route('presupuesto-mensual.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('año', now()->year)
        ->where('mes', now()->month)
    );
});

test('vista mensual con año/mes fuera de rango redirige con flash de error', function () {
    [$user] = usuarioConVistaMensual();

    $response = $this->actingAs($user)->get(route('presupuesto-mensual.index', ['año' => 1999, 'mes' => 10]));

    $response->assertRedirect(route('presupuesto-mensual.index'));
    $response->assertSessionHas('flash.danger', 'El mes solicitado no es válido.');
});
