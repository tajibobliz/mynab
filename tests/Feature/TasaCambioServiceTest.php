<?php

use App\Models\TipoCambio;
use App\Models\User;
use App\Services\TasaCambioService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resolver retorna tasa 1 (string) cuando origen es igual a destino', function () {
    $user = User::factory()->create();
    $service = app(TasaCambioService::class);

    $resultado = $service->resolver($user->id, 'BOB', 'BOB', Carbon::parse('2026-09-01'));

    expect($resultado['tasa'])->toBe('1.00000000')
        ->and($resultado['tasa'])->toBeString()
        ->and($resultado['esExtrapolada'])->toBeFalse();
});

test('resolver retorna la tasa vigente mas reciente hasta la fecha dada', function () {
    $user = User::factory()->create();
    TipoCambio::factory()->for($user)->create([
        'moneda_origen' => 'BOB', 'moneda_destino' => 'USD', 'tasa' => 6.90, 'fecha' => '2026-08-01',
    ]);
    TipoCambio::factory()->for($user)->create([
        'moneda_origen' => 'BOB', 'moneda_destino' => 'USD', 'tasa' => 6.95, 'fecha' => '2026-09-01',
    ]);
    TipoCambio::factory()->for($user)->create([
        'moneda_origen' => 'BOB', 'moneda_destino' => 'USD', 'tasa' => 7.00, 'fecha' => '2026-10-01',
    ]);

    $service = app(TasaCambioService::class);
    $resultado = $service->resolver($user->id, 'BOB', 'USD', Carbon::parse('2026-09-15'));

    expect($resultado['tasa'])->toBe('6.95000000')
        ->and($resultado['tasa'])->toBeString()
        ->and($resultado['esExtrapolada'])->toBeFalse();
});

test('resolver marca esExtrapolada=true cuando no hay tasa historica hasta la fecha', function () {
    $user = User::factory()->create();
    TipoCambio::factory()->for($user)->create([
        'moneda_origen' => 'BOB', 'moneda_destino' => 'USD', 'tasa' => 6.95, 'fecha' => '2026-09-15',
    ]);

    $service = app(TasaCambioService::class);
    $resultado = $service->resolver($user->id, 'BOB', 'USD', Carbon::parse('2026-09-01'));

    expect($resultado['tasa'])->toBe('6.95000000')
        ->and($resultado['esExtrapolada'])->toBeTrue();
});

test('resolver retorna null cuando no existe ninguna tasa para el par', function () {
    $user = User::factory()->create();
    $service = app(TasaCambioService::class);

    $resultado = $service->resolver($user->id, 'BOB', 'USDT', Carbon::parse('2026-09-01'));

    expect($resultado)->toBeNull();
});

test('convertir aplica la tasa correctamente y redondea half-up', function () {
    $user = User::factory()->create();

    TipoCambio::factory()->for($user)->create([
        'moneda_origen' => 'BOB', 'moneda_destino' => 'USD', 'tasa' => 0.14, 'fecha' => '2026-09-01',
    ]);
    TipoCambio::factory()->for($user)->create([
        'moneda_origen' => 'BOB', 'moneda_destino' => 'USDT', 'tasa' => 0.005, 'fecha' => '2026-09-01',
    ]);

    $service = app(TasaCambioService::class);

    // Caso normal: 350000 * 0.14 = 49000 centavos exacto, sin ambigüedad de redondeo.
    expect($service->convertir($user->id, 350000, 'BOB', 'USD', Carbon::parse('2026-09-01')))
        ->toBe(49000);

    // Caso límite: 100 * 0.005 = 0.5 exacto -> half-up redondea a 1, no trunca a 0.
    expect($service->convertir($user->id, 100, 'BOB', 'USDT', Carbon::parse('2026-09-01')))
        ->toBe(1);
});
