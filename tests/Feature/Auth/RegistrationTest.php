<?php

namespace App\Tests\Feature\Auth;

use App\Settings\GeneralSettings;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertViewIs('auth.register');
    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@gmail.com',
        'password' => 'D&#t@EVMXhfkpx*kLv3F',
        'password_confirmation' => 'D&#t@EVMXhfkpx*kLv3F',
    ]);

    $this->assertAuthenticated();

    $response->assertRedirect(route('home'));
});

test('validation rules are adhered to', function () {
    $response = $this->post(route('register'), [
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
});

test('authenticated users get redirected away from register view', function () {
    $user = createUser();

    $response = $this->actingAs($user)->get(route('register'));

    $response->assertRedirect(route('home'));
    $response->assertStatus(302);

    $this->assertAuthenticatedAs($user);
});

test('guests cannot access /register when this feature is disabled', function () {
    GeneralSettings::fake([
        'disable_user_registration' => true
    ]);

    $response = $this->get(route('register'));

    $response->assertRedirect(route('home'));
    $response->assertStatus(302);
});

test('new users cannot register with not strong password', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'testings',
        'password_confirmation' => 'testings',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('new users cannot register with a disposable email address', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'xk3j9qpz@mailinator.com',
        'password' => 'D&#t@EVMXhfkpx*kLv3F',
        'password_confirmation' => 'D&#t@EVMXhfkpx*kLv3F',
    ]);

    $response->assertSessionHasErrors(['email' => 'Disposable email addresses are not allowed.']);
    $this->assertGuest();
});

test('new users cannot register with an email domain without mail servers', function () {
    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'D&#t@EVMXhfkpx*kLv3F',
        'password_confirmation' => 'D&#t@EVMXhfkpx*kLv3F',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
});

test('registration attempts are rate limited per ip', function () {
    foreach (range(1, 10) as $attempt) {
        $this->post(route('register'), ['name' => '', 'email' => '', 'password' => ''])
            ->assertStatus(302);
    }

    $this->post(route('register'), ['name' => '', 'email' => '', 'password' => ''])
        ->assertStatus(429);
});
