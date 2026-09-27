<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
});

test('guests are redirected to the login page from the dashboard', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect(route('login', absolute: false));
});
