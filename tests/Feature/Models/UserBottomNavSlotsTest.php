<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBottomNavSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_returns_default_bottom_nav_slots(): void
    {
        $user = User::create([
            'name' => 'Executive Nav User',
            'email' => 'nav.default@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        $slots = $user->getBottomNavSlots();

        expect($slots)->toBeArray()
            ->and($slots)->toHaveCount(4)
            ->and($slots[0]['key'])->toBe('copilot')
            ->and($slots[1]['key'])->toBe('bundles');
    }

    public function test_user_returns_custom_bottom_nav_slots_when_configured(): void
    {
        $customSlots = [
            ['key' => 'copilot', 'label' => 'AI Copilot', 'icon' => 'heroicon-o-sparkles', 'url' => '/admin/executive-assistant'],
            ['key' => 'projects', 'label' => 'Projects', 'icon' => 'heroicon-o-folder', 'url' => '/admin/project-registers'],
            ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'heroicon-o-check-circle', 'url' => '/admin/personal-tasks'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'heroicon-o-cog-6-tooth', 'url' => '/admin/executive-settings'],
        ];

        $user = User::create([
            'name' => 'Custom Nav User',
            'email' => 'nav.custom@dpik.com.my',
            'password' => bcrypt('password'),
            'bottom_nav_slots' => $customSlots,
        ]);

        $slots = $user->getBottomNavSlots();

        expect($slots)->toBeArray()
            ->and($slots)->toHaveCount(4)
            ->and($slots[1]['key'])->toBe('projects')
            ->and($slots[2]['key'])->toBe('tasks');
    }
}
