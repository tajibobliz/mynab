<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

uses(RefreshDatabase::class);

test('registration screen can be rendered', function () {
    if (! Features::enabled(Features::registration())) {
        $this->markTestSkipped('Registration support is not enabled.');
    }

    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'María Rojas',
        'email' => 'maria@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('users', [
        'email' => 'maria@example.com',
    ]);
});

test('registration fails with a duplicate email', function () {
    User::factory()->create(['email' => 'maria@example.com']);

    $response = $this->post('/register', [
        'name' => 'María Rojas',
        'email' => 'maria@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
});

test('registration fails with a password shorter than 8 characters', function () {
    $response = $this->post('/register', [
        'name' => 'María Rojas',
        'email' => 'maria@example.com',
        'password' => 'short1',
        'password_confirmation' => 'short1',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => 'maria@example.com',
    ]);
});
