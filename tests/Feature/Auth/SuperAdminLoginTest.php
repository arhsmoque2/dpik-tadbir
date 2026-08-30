<?php

use App\Models\User;

test('superadmin can view admin login page when auth is enabled', function () {
    config(['auth.enabled' => true]);

    $response = $this->get('/admin/login');
    $response->assertSuccessful();
});

test('superadmin can authenticate and access admin panel dashboard', function () {
    config(['auth.enabled' => true]);

    $user = User::firstOrCreate(
        ['email' => 'admin@dpik.com.my'],
        [
            'name' => 'Admin DPIK',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]
    );

    $response = $this->actingAs($user)->get('/admin');
    $response->assertSuccessful();
});

test('auto login bypass allows direct admin access when auth is disabled', function () {
    config(['auth.enabled' => false]);

    $response = $this->get('/admin');
    $response->assertSuccessful();

    $loginResponse = $this->get('/admin/login');
    $loginResponse->assertRedirect('/admin');
});
