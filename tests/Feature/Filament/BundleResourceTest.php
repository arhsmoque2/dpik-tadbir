<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BundleResource\Pages\ListBundles;
use App\Models\Bundle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BundleResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_render_bundle_resource_index_table(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin.bundle@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'filter_label' => 'Direct Correspondence · 01 Sep 2026',
            'filter_criteria' => ['direct_only' => true],
            'project_code' => 'PC-2023-011',
            'email_count' => 5,
        ]);

        Livewire::actingAs($user)
            ->test(ListBundles::class)
            ->assertCanSeeTableRecords([$bundle])
            ->assertSee('Direct Correspondence · 01 Sep 2026');
    }
}
