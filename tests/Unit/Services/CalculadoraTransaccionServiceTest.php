<?php

use App\Models\TipoCambio;
use App\Models\User;
use App\Services\CalculadoraTransaccionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(CalculadoraTransaccionService::class);
    $this->user = User::factory()->create();
});

test('identity BOB a BOB retorna el mismo monto sin tasa', function () {
    [$monto, $tasa] = $this->service->resolverMontoMonedaBase(
        montoCentavos: 10000,
        monedaCuenta: 'BOB',
        monedaBase: 'BOB',
        userId: $this->user->id,
        fecha: now(),
    );

    expect($monto)->toBe(10000)
        ->and($tasa)->toBeNull();
});

test('USD a BOB con tasa vigente convierte correctamente', function () {
    TipoCambio::factory()->for($this->user)->create([
        'moneda_origen' => 'USD',
        'moneda_destino' => 'BOB',
        'tasa' => '6.96000000',
        'fecha' => now()->subDays(5)->toDateString(),
    ]);

    [$monto, $tasa] = $this->service->resolverMontoMonedaBase(
        montoCentavos: 10000, // USD 100.00
        monedaCuenta: 'USD',
        monedaBase: 'BOB',
        userId: $this->user->id,
        fecha: now(),
    );

    expect($monto)->toBe(69600) // Bs 696.00
        ->and((string) $tasa)->toBe('6.96000000');
});

test('USDT a BOB usa la tasa vigente a la fecha especifica', function () {
    TipoCambio::factory()->for($this->user)->create([
        'moneda_origen' => 'USDT',
        'moneda_destino' => 'BOB',
        'tasa' => '7.10000000',
        'fecha' => '2026-09-01',
    ]);
    TipoCambio::factory()->for($this->user)->create([
        'moneda_origen' => 'USDT',
        'moneda_destino' => 'BOB',
        'tasa' => '7.20000000',
        'fecha' => '2026-09-20',
    ]);

    // Fecha intermedia: debe resolver la tasa del 2026-09-01 (la vigente
    // más reciente que no sea posterior a la fecha consultada), no la del 20.
    [$monto, $tasa] = $this->service->resolverMontoMonedaBase(
        montoCentavos: 5000,
        monedaCuenta: 'USDT',
        monedaBase: 'BOB',
        userId: $this->user->id,
        fecha: \Carbon\Carbon::parse('2026-09-10'),
    );

    expect((string) $tasa)->toBe('7.10000000')
        ->and($monto)->toBe(35500); // 5000 * 7.10
});

test('resolverMontoDestino identity retorna el mismo monto', function () {
    $monto = $this->service->resolverMontoDestino(
        montoCentavos: 20000,
        monedaOrigen: 'BOB',
        monedaDestino: 'BOB',
        userId: $this->user->id,
        fecha: now(),
    );

    expect($monto)->toBe(20000);
});

test('resolverMontoDestino convierte correctamente entre monedas distintas', function () {
    TipoCambio::factory()->for($this->user)->create([
        'moneda_origen' => 'USDT',
        'moneda_destino' => 'BOB',
        'tasa' => '7.00000000',
        'fecha' => now()->subDay()->toDateString(),
    ]);

    $monto = $this->service->resolverMontoDestino(
        montoCentavos: 10000, // USDT 100.00
        monedaOrigen: 'USDT',
        monedaDestino: 'BOB',
        userId: $this->user->id,
        fecha: now(),
    );

    expect($monto)->toBe(70000); // Bs 700.00
});

test('resolverMontoMonedaBase sin tasa disponible lanza RuntimeException', function () {
    $this->service->resolverMontoMonedaBase(
        montoCentavos: 10000,
        monedaCuenta: 'USD',
        monedaBase: 'BOB',
        userId: $this->user->id, // usuario sin ningun TipoCambio registrado
        fecha: now(),
    );
})->throws(RuntimeException::class, 'No hay tipo de cambio disponible para convertir de USD a BOB.');
