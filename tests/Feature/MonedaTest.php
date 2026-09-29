<?php

use App\Models\Moneda;
use App\Models\Presupuesto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('MonedaController index muestra las 3 monedas del catalogo', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('monedas.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Monedas/Index')
        ->has('monedas', 3)
    );
});

test('MonedaController toggle activa y desactiva una moneda sin uso', function () {
    $user = User::factory()->create();
    $moneda = Moneda::factory()->create();

    $this->actingAs($user)->post(route('monedas.toggle', $moneda));
    expect($moneda->fresh()->activa)->toBeFalse();

    $this->actingAs($user)->post(route('monedas.toggle', $moneda));
    expect($moneda->fresh()->activa)->toBeTrue();
});

test('MonedaController toggle rechaza desactivar una moneda en uso por un presupuesto', function () {
    $user = User::factory()->create();
    $moneda = Moneda::factory()->create();
    Presupuesto::factory()->for($user)->create(['moneda_base_codigo' => $moneda->codigo]);

    $response = $this->actingAs($user)->post(route('monedas.toggle', $moneda));

    $response->assertRedirect();
    $response->assertSessionHas('flash.danger');
    expect($moneda->fresh()->activa)->toBeTrue();
});

test('monedasActivas se comparte a todas las paginas via Inertia', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->has('monedasActivas', 3));
});
