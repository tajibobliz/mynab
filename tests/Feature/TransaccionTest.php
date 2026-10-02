<?php

use App\Enums\TipoTransaccion;
use App\Models\Beneficiario;
use App\Models\Categoria;
use App\Models\Cuenta;
use App\Models\GrupoCategoria;
use App\Models\Presupuesto;
use App\Models\TipoCambio;
use App\Models\Transaccion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

// Reutiliza el helper global ya declarado en CuentaTest.php (Change 4) —
// mismo patrón que Changes 5 y 6, no se redeclara.

/**
 * Cuenta/cuentaDestino con moneda_codigo IGUAL a moneda_base_codigo del
 * presupuesto (identity): así validarTasaDisponible() nunca interfiere en
 * tests que no son específicamente sobre conversión multi-moneda. Los tests
 * que SÍ prueban conversión (12.4, 12.19, 12.24) arman su propio fixture
 * con monedas distintas + TipoCambio registrado.
 */
function fixturesTransaccion(): array
{
    [$user, $presupuesto] = usuarioConPresupuestoActivo();

    $cuenta = Cuenta::factory()->for($presupuesto)->create(['moneda_codigo' => $presupuesto->moneda_base_codigo]);
    $cuentaDestino = Cuenta::factory()->for($presupuesto)->create(['moneda_codigo' => $presupuesto->moneda_base_codigo]);
    $grupo = GrupoCategoria::factory()->for($presupuesto, 'presupuesto')->create();
    $categoria = Categoria::factory()->for($grupo, 'grupoCategoria')->create();
    $categoria2 = Categoria::factory()->for($grupo, 'grupoCategoria')->create();
    $beneficiario = Beneficiario::factory()->for($presupuesto, 'presupuesto')->create();

    return compact('user', 'presupuesto', 'cuenta', 'cuentaDestino', 'categoria', 'categoria2', 'beneficiario');
}

// ---------------------------------------------------------------------
// 12A — Store válido por tipo (12.1-12.5)
// ---------------------------------------------------------------------
describe('12A - store valido por tipo', function () {
    test('12.1 store outflow simple valido', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id,
            'tipo' => 'outflow',
            'categoria_id' => $categoria->id,
            'monto_centavos' => 10000,
            'fecha_hora' => now()->toDateTimeString(),
            'es_split' => false,
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $this->assertDatabaseHas('transacciones', [
            'cuenta_id' => $cuenta->id, 'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
        ]);
    });

    test('12.2 store inflow valido sin categoria', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'beneficiario' => $beneficiario] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id,
            'tipo' => 'inflow',
            'beneficiario_id' => $beneficiario->id,
            'monto_centavos' => 350000,
            'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $this->assertDatabaseHas('transacciones', [
            'cuenta_id' => $cuenta->id, 'tipo' => 'inflow', 'categoria_id' => null, 'monto_centavos' => 350000,
        ]);
    });

    test('12.3 store transfer valido entre cuentas misma moneda', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'cuentaDestino' => $cuentaDestino] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id,
            'cuenta_destino_id' => $cuentaDestino->id,
            'tipo' => 'transfer',
            'monto_centavos' => 10000,
            'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $this->assertDatabaseHas('transacciones', [
            'cuenta_id' => $cuenta->id, 'cuenta_destino_id' => $cuentaDestino->id, 'tipo' => 'transfer',
            'monto_centavos' => 10000, 'monto_centavos_destino' => 10000,
        ]);
    });

    test('12.4 store transfer valido entre cuentas distinta moneda calcula monto_destino', function () {
        [$user, $presupuesto] = usuarioConPresupuestoActivo();
        $presupuesto->update(['moneda_base_codigo' => 'BOB']);
        $cuenta = Cuenta::factory()->for($presupuesto)->create(['moneda_codigo' => 'BOB']);
        $cuentaDestino = Cuenta::factory()->for($presupuesto)->create(['moneda_codigo' => 'USDT']);
        TipoCambio::factory()->for($user)->create([
            'moneda_origen' => 'BOB', 'moneda_destino' => 'USDT', 'tasa' => '0.14000000', 'fecha' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id,
            'cuenta_destino_id' => $cuentaDestino->id,
            'tipo' => 'transfer',
            'monto_centavos' => 10000, // Bs 100.00
            'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $creada = Transaccion::where('cuenta_id', $cuenta->id)->where('tipo', 'transfer')->first();
        expect($creada->monto_centavos_destino)->toBe(1400); // 10000 * 0.14
    });

    test('12.5 store outflow split con 2 lineas suma correcta', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria, 'categoria2' => $categoria2] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id,
            'tipo' => 'outflow',
            'es_split' => true,
            'monto_centavos' => 10000,
            'fecha_hora' => now()->toDateTimeString(),
            'splits' => [
                ['categoria_id' => $categoria->id, 'monto_centavos' => 6000],
                ['categoria_id' => $categoria2->id, 'monto_centavos' => 4000],
            ],
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $creada = Transaccion::where('es_split', true)->first();
        expect($creada)->not->toBeNull()
            ->and($creada->categoria_id)->toBeNull()
            ->and($creada->splits()->count())->toBe(2);
    });
});

// ---------------------------------------------------------------------
// 12B — Validación de split (12.6-12.7)
// ---------------------------------------------------------------------
describe('12B - validacion de split', function () {
    test('12.6 store outflow split suma incorrecta falla', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria, 'categoria2' => $categoria2] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'outflow', 'es_split' => true,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
            'splits' => [
                ['categoria_id' => $categoria->id, 'monto_centavos' => 6000],
                ['categoria_id' => $categoria2->id, 'monto_centavos' => 5000],
            ],
        ]);

        $response->assertSessionHasErrors([
            'splits' => 'La suma de las líneas (Bs 11000) no coincide con el total (Bs 10000).',
        ]);
        $this->assertDatabaseCount('transacciones', 0);
    });

    test('12.7 store outflow split con 1 linea falla minimo 2', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'outflow', 'es_split' => true,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
            'splits' => [
                ['categoria_id' => $categoria->id, 'monto_centavos' => 10000],
            ],
        ]);

        $response->assertSessionHasErrors([
            'splits' => 'Debes agregar al menos 2 líneas para dividir la transacción.',
        ]);
    });
});

// ---------------------------------------------------------------------
// 12C — Reglas condicionales por tipo (12.8-12.13)
// ---------------------------------------------------------------------
describe('12C - reglas condicionales por tipo', function () {
    test('12.8 store transfer con cuenta_destino igual a origen falla', function () {
        ['user' => $user, 'cuenta' => $cuenta] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'cuenta_destino_id' => $cuenta->id, 'tipo' => 'transfer',
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors([
            'cuenta_destino_id' => 'La cuenta destino debe ser distinta a la cuenta de origen.',
        ]);
    });

    test('12.9 store outflow sin categoria no split falla', function () {
        ['user' => $user, 'cuenta' => $cuenta] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'outflow', 'es_split' => false,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors(['categoria_id' => 'La categoría es requerida.']);
    });

    test('12.10 store inflow con categoria falla', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria, 'beneficiario' => $beneficiario] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'inflow', 'beneficiario_id' => $beneficiario->id,
            'categoria_id' => $categoria->id, 'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors(['categoria_id' => 'La categoría no aplica para este tipo de transacción.']);
    });

    test('12.11 store inflow sin beneficiario falla', function () {
        ['user' => $user, 'cuenta' => $cuenta] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'inflow',
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors(['beneficiario_id' => 'El beneficiario es requerido para un ingreso.']);
    });

    test('12.12 store transfer con categoria falla', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'cuentaDestino' => $cuentaDestino, 'categoria' => $categoria] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'cuenta_destino_id' => $cuentaDestino->id, 'tipo' => 'transfer',
            'categoria_id' => $categoria->id, 'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors(['categoria_id' => 'La categoría no aplica para este tipo de transacción.']);
    });

    test('12.13 store transfer con beneficiario falla', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'cuentaDestino' => $cuentaDestino, 'beneficiario' => $beneficiario] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'cuenta_destino_id' => $cuentaDestino->id, 'tipo' => 'transfer',
            'beneficiario_id' => $beneficiario->id, 'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors(['beneficiario_id' => 'El beneficiario no aplica para este tipo de transacción.']);
    });
});

// ---------------------------------------------------------------------
// 12D — IDOR (12.14-12.16)
// ---------------------------------------------------------------------
describe('12D - IDOR por FK', function () {
    test('12.14 store con cuenta_id de otro usuario falla', function () {
        ['user' => $user] = fixturesTransaccion();
        $otro = User::factory()->create();
        $otroPresupuesto = Presupuesto::factory()->for($otro)->create();
        $cuentaAjena = Cuenta::factory()->for($otroPresupuesto)->create();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuentaAjena->id, 'tipo' => 'outflow',
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors([
            'cuenta_id' => 'La cuenta seleccionada no existe o no pertenece a tu presupuesto activo.',
        ]);
    });

    test('12.15 store con categoria_id de otro presupuesto falla', function () {
        ['user' => $user, 'cuenta' => $cuenta] = fixturesTransaccion();
        $otro = User::factory()->create();
        $otroPresupuesto = Presupuesto::factory()->for($otro)->create();
        $grupoAjeno = GrupoCategoria::factory()->for($otroPresupuesto, 'presupuesto')->create();
        $categoriaAjena = Categoria::factory()->for($grupoAjeno, 'grupoCategoria')->create();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'outflow', 'categoria_id' => $categoriaAjena->id,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors([
            'categoria_id' => 'La categoría seleccionada no existe o no pertenece a tu presupuesto activo.',
        ]);
    });

    test('12.16 store con beneficiario_id de otro presupuesto falla', function () {
        ['user' => $user, 'cuenta' => $cuenta] = fixturesTransaccion();
        $otro = User::factory()->create();
        $otroPresupuesto = Presupuesto::factory()->for($otro)->create();
        $beneficiarioAjeno = Beneficiario::factory()->for($otroPresupuesto, 'presupuesto')->create();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'inflow', 'beneficiario_id' => $beneficiarioAjeno->id,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors([
            'beneficiario_id' => 'El beneficiario seleccionado no existe o no pertenece a tu presupuesto activo.',
        ]);
    });
});

// ---------------------------------------------------------------------
// 12E — Validaciones generales (12.17-12.18)
// ---------------------------------------------------------------------
describe('12E - validaciones generales', function () {
    test('12.17 store con fecha futura mayor a 1 dia falla', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'outflow', 'categoria_id' => $categoria->id,
            'monto_centavos' => 10000, 'fecha_hora' => now()->addDays(3)->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors([
            'fecha_hora' => 'La fecha no puede ser mayor a un día en el futuro.',
        ]);
    });

    test('12.18 store con monto 0 falla', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'outflow', 'categoria_id' => $categoria->id,
            'monto_centavos' => 0, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertSessionHasErrors(['monto_centavos' => 'El monto debe ser mayor a cero.']);
    });
});

// ---------------------------------------------------------------------
// 12F — Conversión multi-moneda (12.19-12.20)
// ---------------------------------------------------------------------
describe('12F - conversion multi-moneda', function () {
    test('12.19 conversion implicita USD a BOB con tasa 6.96', function () {
        [$user, $presupuesto] = usuarioConPresupuestoActivo();
        $presupuesto->update(['moneda_base_codigo' => 'BOB']);
        $cuenta = Cuenta::factory()->for($presupuesto)->create(['moneda_codigo' => 'USD']);
        $grupo = GrupoCategoria::factory()->for($presupuesto, 'presupuesto')->create();
        $categoria = Categoria::factory()->for($grupo, 'grupoCategoria')->create();
        TipoCambio::factory()->for($user)->create([
            'moneda_origen' => 'USD', 'moneda_destino' => 'BOB', 'tasa' => '6.96000000', 'fecha' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'outflow', 'categoria_id' => $categoria->id,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(), // USD 100.00
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $creada = Transaccion::where('cuenta_id', $cuenta->id)->first();
        expect($creada->monto_moneda_base_centavos)->toBe(69600) // Bs 696.00
            ->and((string) $creada->tasa_cambio_aplicada)->toBe('6.96000000');
    });

    test('12.20 identity cuenta y presupuesto misma moneda sin tasa', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();

        $response = $this->actingAs($user)->post(route('transacciones.store'), [
            'cuenta_id' => $cuenta->id, 'tipo' => 'outflow', 'categoria_id' => $categoria->id,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $creada = Transaccion::where('cuenta_id', $cuenta->id)->first();
        expect($creada->monto_moneda_base_centavos)->toBe(10000)
            ->and($creada->tasa_cambio_aplicada)->toBeNull();
    });
});

// ---------------------------------------------------------------------
// 12G — Update (12.21-12.24)
// ---------------------------------------------------------------------
describe('12G - update', function () {
    test('12.21 update permite cambiar monto fecha categoria beneficiario notas', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria, 'categoria2' => $categoria2, 'beneficiario' => $beneficiario] = fixturesTransaccion();
        $transaccion = $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 10000, 'fecha_hora' => now()->subDays(2), 'es_split' => false,
        ]);

        $response = $this->actingAs($user)->put(route('transacciones.update', $transaccion), [
            'cuenta_id' => $cuenta->id, 'categoria_id' => $categoria2->id, 'beneficiario_id' => $beneficiario->id,
            'monto_centavos' => 20000, 'fecha_hora' => now()->subDay()->toDateTimeString(), 'notas' => 'actualizado',
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $transaccion->refresh();
        expect($transaccion->monto_centavos)->toBe(20000)
            ->and($transaccion->categoria_id)->toBe($categoria2->id)
            ->and($transaccion->beneficiario_id)->toBe($beneficiario->id)
            ->and($transaccion->notas)->toBe('actualizado');
    });

    test('12.22 update no permite cambiar tipo', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();
        $transaccion = $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 10000, 'fecha_hora' => now(), 'es_split' => false,
        ]);

        $response = $this->actingAs($user)->put(route('transacciones.update', $transaccion), [
            'cuenta_id' => $cuenta->id, 'categoria_id' => $categoria->id,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
            'tipo' => 'inflow', // intento de manipulacion, debe ser ignorado
        ]);

        $response->assertRedirect(route('transacciones.index'));
        expect($transaccion->fresh()->tipo)->toBe(TipoTransaccion::Outflow);
    });

    test('12.23 update no permite cambiar es_split', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();
        $transaccion = $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 10000, 'fecha_hora' => now(), 'es_split' => false,
        ]);

        $response = $this->actingAs($user)->put(route('transacciones.update', $transaccion), [
            'cuenta_id' => $cuenta->id, 'categoria_id' => $categoria->id,
            'monto_centavos' => 10000, 'fecha_hora' => now()->toDateTimeString(),
            'es_split' => true, // intento de manipulacion, debe ser ignorado
        ]);

        $response->assertRedirect(route('transacciones.index'));
        expect($transaccion->fresh()->es_split)->toBeFalse();
    });

    test('12.24 update recalcula monto_moneda_base al cambiar fecha', function () {
        [$user, $presupuesto] = usuarioConPresupuestoActivo();
        $presupuesto->update(['moneda_base_codigo' => 'BOB']);
        $cuenta = Cuenta::factory()->for($presupuesto)->create(['moneda_codigo' => 'USD']);
        $grupo = GrupoCategoria::factory()->for($presupuesto, 'presupuesto')->create();
        $categoria = Categoria::factory()->for($grupo, 'grupoCategoria')->create();

        TipoCambio::factory()->for($user)->create([
            'moneda_origen' => 'USD', 'moneda_destino' => 'BOB', 'tasa' => '6.90000000', 'fecha' => '2026-09-01',
        ]);
        TipoCambio::factory()->for($user)->create([
            'moneda_origen' => 'USD', 'moneda_destino' => 'BOB', 'tasa' => '7.00000000', 'fecha' => '2026-09-20',
        ]);

        $transaccion = $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 69000, 'tasa_cambio_aplicada' => '6.90000000',
            'fecha_hora' => '2026-09-05', 'es_split' => false,
        ]);

        $response = $this->actingAs($user)->put(route('transacciones.update', $transaccion), [
            'cuenta_id' => $cuenta->id, 'categoria_id' => $categoria->id,
            'monto_centavos' => 10000, 'fecha_hora' => '2026-09-25T10:00:00',
        ]);

        $response->assertRedirect(route('transacciones.index'));
        $transaccion->refresh();
        expect($transaccion->monto_moneda_base_centavos)->toBe(70000)
            ->and((string) $transaccion->tasa_cambio_aplicada)->toBe('7.00000000');
    });
});

// ---------------------------------------------------------------------
// 12H — Destroy + Policy (12.25-12.27)
// ---------------------------------------------------------------------
describe('12H - destroy y policy', function () {
    test('12.25 destroy hace soft delete en cascada a splits', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria, 'categoria2' => $categoria2] = fixturesTransaccion();
        $transaccion = $cuenta->transacciones()->create([
            'tipo' => 'outflow', 'monto_centavos' => 10000, 'monto_moneda_base_centavos' => 10000,
            'fecha_hora' => now(), 'es_split' => true,
        ]);
        $split1 = $transaccion->splits()->create(['categoria_id' => $categoria->id, 'monto_centavos' => 6000]);
        $split2 = $transaccion->splits()->create(['categoria_id' => $categoria2->id, 'monto_centavos' => 4000]);

        $response = $this->actingAs($user)->delete(route('transacciones.destroy', $transaccion));

        $response->assertRedirect(route('transacciones.index'));
        $this->assertSoftDeleted('transacciones', ['id' => $transaccion->id]);
        $this->assertSoftDeleted('transacciones_split', ['id' => $split1->id]);
        $this->assertSoftDeleted('transacciones_split', ['id' => $split2->id]);
    });

    test('12.26 policy no puede editar transaccion ajena', function () {
        $dueno = User::factory()->create();
        $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
        $cuentaDueno = Cuenta::factory()->for($presupuestoDueno)->create();
        $transaccion = $cuentaDueno->transacciones()->create([
            'tipo' => 'outflow', 'monto_centavos' => 10000, 'monto_moneda_base_centavos' => 10000,
            'fecha_hora' => now(), 'es_split' => false,
        ]);

        ['user' => $otro] = fixturesTransaccion();

        $response = $this->actingAs($otro)->put(route('transacciones.update', $transaccion), [
            'cuenta_id' => $cuentaDueno->id, 'monto_centavos' => 20000, 'fecha_hora' => now()->toDateTimeString(),
        ]);

        $response->assertForbidden();
    });

    test('12.27 policy no puede eliminar transaccion ajena', function () {
        $dueno = User::factory()->create();
        $presupuestoDueno = Presupuesto::factory()->for($dueno)->create();
        $cuentaDueno = Cuenta::factory()->for($presupuestoDueno)->create();
        $transaccion = $cuentaDueno->transacciones()->create([
            'tipo' => 'outflow', 'monto_centavos' => 10000, 'monto_moneda_base_centavos' => 10000,
            'fecha_hora' => now(), 'es_split' => false,
        ]);

        $otro = User::factory()->create();

        $response = $this->actingAs($otro)->delete(route('transacciones.destroy', $transaccion));

        $response->assertForbidden();
        $this->assertDatabaseHas('transacciones', ['id' => $transaccion->id, 'deleted_at' => null]);
    });
});

// ---------------------------------------------------------------------
// 12I — Index + filtros (12.28-12.30)
// ---------------------------------------------------------------------
describe('12I - index y filtros', function () {
    test('12.28 index filtra por presupuesto activo', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();
        $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 10000, 'fecha_hora' => now(), 'es_split' => false,
        ]);

        $otro = User::factory()->create();
        $presupuestoAjeno = Presupuesto::factory()->for($otro)->create();
        $cuentaAjena = Cuenta::factory()->for($presupuestoAjeno)->create();
        $cuentaAjena->transacciones()->create([
            'tipo' => 'outflow', 'monto_centavos' => 5000, 'monto_moneda_base_centavos' => 5000,
            'fecha_hora' => now(), 'es_split' => false,
        ]);

        $response = $this->actingAs($user)->get(route('transacciones.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Transacciones/Index')
            ->has('transacciones.data', 1)
        );
    });

    test('12.29 index con filtro tipo retorna solo ese tipo', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria, 'beneficiario' => $beneficiario] = fixturesTransaccion();
        $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 10000, 'fecha_hora' => now(), 'es_split' => false,
        ]);
        $cuenta->transacciones()->create([
            'beneficiario_id' => $beneficiario->id, 'tipo' => 'inflow', 'monto_centavos' => 20000,
            'monto_moneda_base_centavos' => 20000, 'fecha_hora' => now(), 'es_split' => false,
        ]);

        $response = $this->actingAs($user)->get(route('transacciones.index', ['tipo' => 'outflow']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Transacciones/Index')
            ->has('transacciones.data', 1)
            ->where('transacciones.data.0.tipo', 'outflow')
        );
    });

    test('12.30 index con filtro desde hasta aplica correctamente', function () {
        ['user' => $user, 'cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();
        $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 10000, 'fecha_hora' => now()->subDays(40), 'es_split' => false,
        ]);
        $dentroDelRango = $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 20000,
            'monto_moneda_base_centavos' => 20000, 'fecha_hora' => now()->subDays(5), 'es_split' => false,
        ]);

        $response = $this->actingAs($user)->get(route('transacciones.index', [
            'desde' => now()->subDays(10)->toDateString(),
            'hasta' => now()->toDateString(),
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Transacciones/Index')
            ->has('transacciones.data', 1)
            ->where('transacciones.data.0.id', $dentroDelRango->id)
        );
    });
});

// ---------------------------------------------------------------------
// 12J — Saldo + cascade FK (12.31-12.32)
// ---------------------------------------------------------------------
describe('12J - saldo y cascade FK', function () {
    test('12.31 cuenta saldoActualCentavos refleja transacciones', function () {
        ['cuenta' => $cuenta, 'cuentaDestino' => $cuentaDestino, 'categoria' => $categoria] = fixturesTransaccion();
        $cuenta->update(['saldo_inicial_centavos' => 100000]);

        $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 20000,
            'monto_moneda_base_centavos' => 20000, 'fecha_hora' => now(), 'es_split' => false,
        ]);
        $cuenta->transacciones()->create([
            'tipo' => 'inflow', 'monto_centavos' => 50000, 'monto_moneda_base_centavos' => 50000,
            'fecha_hora' => now(), 'es_split' => false,
        ]);
        $cuenta->transacciones()->create([
            'cuenta_destino_id' => $cuentaDestino->id, 'tipo' => 'transfer', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 10000, 'monto_centavos_destino' => 10000, 'fecha_hora' => now(), 'es_split' => false,
        ]);
        $cuentaDestino->transacciones()->create([
            'cuenta_destino_id' => $cuenta->id, 'tipo' => 'transfer', 'monto_centavos' => 5000,
            'monto_moneda_base_centavos' => 5000, 'monto_centavos_destino' => 5000, 'fecha_hora' => now(), 'es_split' => false,
        ]);

        // 100000 + 50000(inflow) - 20000(outflow) - 10000(transferOut) + 5000(transferIn) = 125000
        expect($cuenta->fresh()->saldo_actual_centavos)->toBe(125000);
    });

    test('12.32 forceDelete cuenta falla si tiene transacciones', function () {
        ['cuenta' => $cuenta, 'categoria' => $categoria] = fixturesTransaccion();
        $cuenta->transacciones()->create([
            'categoria_id' => $categoria->id, 'tipo' => 'outflow', 'monto_centavos' => 10000,
            'monto_moneda_base_centavos' => 10000, 'fecha_hora' => now(), 'es_split' => false,
        ]);

        expect(fn () => $cuenta->forceDelete())->toThrow(QueryException::class);
    });
});
