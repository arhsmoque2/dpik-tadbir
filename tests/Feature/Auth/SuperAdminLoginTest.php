<?php

use App\Models\User;

test('superadmin can view admin login page', function () {
    $response = $this->get('/admin/login');
    $response->assertSuccessful();
});

test('superadmin can authenticate and access admin panel dashboard', function () {
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
