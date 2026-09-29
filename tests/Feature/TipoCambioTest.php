<?php

use App\Models\TipoCambio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('TipoCambioController store crea un tipo de cambio valido', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tipos-cambio.store'), [
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
        'tasa' => 6.95,
        'fecha' => '2026-09-01',
    ]);

    $response->assertRedirect(route('tipos-cambio.index'));
    // No se compara 'fecha' dentro de assertDatabaseHas: el cast `date` del
    // modelo normaliza a "2026-09-01 00:00:00" al guardar (aunque la columna
    // sea DATE), así que un match exacto contra "2026-09-01" fallaría en
    // SQLite (no trunca el string como sí lo haría Postgres). whereDate() es
    // portable entre ambos motores.
    $this->assertDatabaseHas('tipos_cambio', [
        'user_id' => $user->id,
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
    ]);
    expect(TipoCambio::whereDate('fecha', '2026-09-01')->exists())->toBeTrue();
});

test('TipoCambioController store rechaza moneda_origen igual a moneda_destino', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tipos-cambio.store'), [
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'BOB',
        'tasa' => 1,
        'fecha' => '2026-09-01',
    ]);

    $response->assertSessionHasErrors('moneda_destino');
    $this->assertDatabaseCount('tipos_cambio', 0);
});

test('TipoCambioController store rechaza fecha futura', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tipos-cambio.store'), [
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
        'tasa' => 6.95,
        'fecha' => now()->addDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('fecha');
    $this->assertDatabaseCount('tipos_cambio', 0);
});

test('TipoCambioController store rechaza tasa menor o igual a cero', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tipos-cambio.store'), [
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
        'tasa' => 0,
        'fecha' => '2026-09-01',
    ]);

    $response->assertSessionHasErrors('tasa');
    $this->assertDatabaseCount('tipos_cambio', 0);
});

test('TipoCambioController store rechaza duplicado de user+origen+destino+fecha', function () {
    $user = User::factory()->create();
    $fecha = CarbonImmutable::parse('2026-09-01');

    TipoCambio::factory()->for($user)->create([
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
        'fecha' => $fecha,
    ]);

    $response = $this->actingAs($user)->post(route('tipos-cambio.store'), [
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
        'tasa' => 6.99,
        'fecha' => $fecha->toDateString(),
    ]);

    $response->assertSessionHasErrors('fecha');
    $this->assertDatabaseCount('tipos_cambio', 1);
});

test('TipoCambioController update permite editar tasa y fecha pero ignora origen y destino', function () {
    $user = User::factory()->create();
    $tc = TipoCambio::factory()->for($user)->create([
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
        'tasa' => 6.90,
        'fecha' => '2026-09-01',
    ]);

    $response = $this->actingAs($user)->put(route('tipos-cambio.update', $tc), [
        'moneda_origen' => 'USDT',
        'moneda_destino' => 'BOB',
        'tasa' => 7.10,
        'fecha' => '2026-09-05',
    ]);

    $response->assertRedirect(route('tipos-cambio.index'));
    $tc->refresh();
    expect($tc->moneda_origen)->toBe('BOB')
        ->and($tc->moneda_destino)->toBe('USD')
        ->and($tc->tasa)->toBe('7.10000000')
        ->and($tc->fecha->toDateString())->toBe('2026-09-05');
});

test('TipoCambioController destroy elimina el registro permanentemente (hard delete)', function () {
    $user = User::factory()->create();
    $tc = TipoCambio::factory()->for($user)->create();

    $response = $this->actingAs($user)->delete(route('tipos-cambio.destroy', $tc));

    $response->assertRedirect(route('tipos-cambio.index'));
    $this->assertDatabaseMissing('tipos_cambio', ['id' => $tc->id]);
});

test('un usuario no puede editar un tipo de cambio ajeno', function () {
    $dueno = User::factory()->create();
    $tc = TipoCambio::factory()->for($dueno)->create();

    $otro = User::factory()->create();
    $response = $this->actingAs($otro)->put(route('tipos-cambio.update', $tc), [
        'tasa' => 1,
        'fecha' => '2026-09-01',
    ]);

    $response->assertForbidden();
});

test('TipoCambioFactory garantiza moneda_origen distinta de moneda_destino', function () {
    $registros = TipoCambio::factory()->count(20)->create();

    expect($registros->every(fn (TipoCambio $tc) => $tc->moneda_origen !== $tc->moneda_destino))->toBeTrue();
});

test('TipoCambioController store acepta tasa en el limite superior permitido (99999999)', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tipos-cambio.store'), [
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
        'tasa' => 99999999,
        'fecha' => '2026-09-01',
    ]);

    $response->assertRedirect(route('tipos-cambio.index'));
    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseCount('tipos_cambio', 1);
});

test('TipoCambioController store acepta tasa en el limite inferior permitido (0.00000001)', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tipos-cambio.store'), [
        'moneda_origen' => 'BOB',
        'moneda_destino' => 'USD',
        'tasa' => 0.00000001,
        'fecha' => '2026-09-01',
    ]);

    $response->assertRedirect(route('tipos-cambio.index'));
    $response->assertSessionDoesntHaveErrors();
    $this->assertDatabaseCount('tipos_cambio', 1);
});
