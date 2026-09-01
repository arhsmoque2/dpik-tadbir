<?php

use App\Models\Bundle;
use App\Models\BundleEmail;
use App\Models\User;

test('bundle can be created with metadata and emails relationship', function () {
    $user = User::create([
        'name' => 'Executive Test User',
        'email' => 'executive.bundle@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'super_admin',
    ]);

    $bundle = Bundle::create([
        'user_id' => $user->id,
        'filter_label' => 'Direct Correspondence · 01 Sep 2026',
        'filter_criteria' => [
            'sent_after' => '2026-09-01',
            'direct_only' => true,
            'delta_only' => true,
        ],
        'project_code' => 'PC-2023-011',
        'email_count' => 1,
        'notes' => 'Verified Pier 4 soil tests.',
    ]);

    expect($bundle)->not->toBeNull()
        ->and($bundle->filter_label)->toBe('Direct Correspondence · 01 Sep 2026')
        ->and($bundle->filter_criteria)->toBeArray()
        ->and($bundle->filter_criteria['direct_only'])->toBeTrue()
        ->and($bundle->project_code)->toBe('PC-2023-011');

    $email = BundleEmail::create([
        'bundle_id' => $bundle->id,
        'message_id' => 'MSG_TEST_001',
        'from_name' => 'Ir. Dr. Tan',
        'from_email' => 'dr.tan@geotech-consult.com',
        'subject' => 'Re: Pier 4 Geotechnical VO Confirmation',
        'snippet' => 'Soil test analysis for Pier 4 indicates RM 120k VO estimation.',
        'received_at' => now(),
    ]);

    expect($bundle->bundleEmails)->toHaveCount(1)
        ->and($bundle->bundleEmails->first()->message_id)->toBe('MSG_TEST_001')
        ->and($email->bundle->id)->toBe($bundle->id)
        ->and($bundle->user->id)->toBe($user->id);
});
