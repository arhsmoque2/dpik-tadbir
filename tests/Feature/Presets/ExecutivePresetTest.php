<?php

use App\Models\ExecutivePreset;
use App\Models\User;
use App\Policies\ExecutivePresetPolicy;
use App\Services\Presets\PresetExecutionService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('executive preset policy allows owner and super admin access', function () {
    $admin = User::create([
        'name' => 'Super Admin',
        'email' => 'admin@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'super_admin',
    ]);

    $user = User::create([
        'name' => 'Executive User',
        'email' => 'exec@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'managing_director',
    ]);

    $systemPreset = ExecutivePreset::create([
        'user_id' => null,
        'name' => 'Morning Delta Briefing',
        'trigger_phrase' => 'morning delta',
        'prompt_template' => 'Analyze all unread emails since yesterday.',
        'target_tools' => ['outlook_list_inbox_delta'],
    ]);

    $userPreset = ExecutivePreset::create([
        'user_id' => $user->id,
        'name' => 'Client Review',
        'trigger_phrase' => 'review client',
        'prompt_template' => 'Review client {client} emails.',
        'target_tools' => ['outlook_search_mail'],
    ]);

    $policy = new ExecutivePresetPolicy;

    expect($policy->viewAny($user))->toBeTrue();
    expect($policy->create($user))->toBeTrue();
    expect($policy->view($user, $systemPreset))->toBeTrue();
    expect($policy->view($user, $userPreset))->toBeTrue();
    expect($policy->update($user, $userPreset))->toBeTrue();
    expect($policy->delete($user, $userPreset))->toBeTrue();

    // User cannot delete system preset, but super admin can
    expect($policy->delete($user, $systemPreset))->toBeFalse();
    expect($policy->delete($admin, $systemPreset))->toBeTrue();
    expect($policy->update($admin, $systemPreset))->toBeTrue();
});

test('preset execution service compiles templates with variables', function () {
    $service = new PresetExecutionService;
    $preset = new ExecutivePreset([
        'name' => 'Claim Validator',
        'prompt_template' => 'Review claim for project {project_code} submitted by {contractor}.',
    ]);

    $compiled = $service->compilePrompt($preset, [
        'project_code' => 'PC-2023-011',
        'contractor' => 'Minco Perunding',
    ]);

    expect($compiled)->toContain('PC-2023-011');
    expect($compiled)->toContain('Minco Perunding');
    expect($preset->user())->toBeInstanceOf(BelongsTo::class);
});
