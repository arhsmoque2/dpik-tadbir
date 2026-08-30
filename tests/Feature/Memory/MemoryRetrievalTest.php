<?php

use App\Models\ProjectRegistryEntry;
use App\Models\User;
use App\Services\Memory\MemoryRetrievalService;

test('memory retrieval returns relevant project records and dense format', function () {
    $user = User::create([
        'name' => 'Admin Exec',
        'email' => 'admin@dpik.com.my',
        'password' => bcrypt('secret'),
    ]);

    ProjectRegistryEntry::create([
        'project_code' => 'FT264',
        'project_name' => 'Projek Jambatan Batang Lupar',
        'summary' => 'Mesyuarat tapak disahkan pada 15hb. JKR minta submission BQ segera.',
        'decisions' => ['Submission BQ pada 20hb'],
        'commitments' => ['Draft laporan awal siap Jumaat'],
        'source_type' => 'email_summary',
        'user_id' => $user->id,
        'recorded_at' => now(),
    ]);

    $service = app(MemoryRetrievalService::class);
    $results = $service->search('Batang Lupar');

    expect($results)->toHaveCount(1);
    expect($results->first()->projectCode)->toBe('FT264');

    $dense = $service->formatAsDenseContext($results);
    expect($dense)->toContain('project:FT264');
    expect($dense)->toContain('dm:decision');
});
