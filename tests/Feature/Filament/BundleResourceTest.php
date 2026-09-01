<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BundleResource;
use App\Filament\Resources\BundleResource\Pages\EditBundle;
use App\Filament\Resources\BundleResource\Pages\ListBundles;
use App\Filament\Resources\BundleResource\Pages\ViewBundle;
use App\Models\Bundle;
use App\Models\BundleEmail;
use App\Models\User;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
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

    public function test_bundle_resource_provides_valid_form_table_infolist_and_pages(): void
    {
        $user = User::create([
            'name' => 'Admin User 2',
            'email' => 'admin2.bundle@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);
        $this->actingAs($user);

        $formSchema = BundleResource::form(new Schema);
        expect($formSchema->getComponents())->not->toBeEmpty();

        $table = BundleResource::table(Table::make(new ListBundles));
        expect($table->getColumns())->not->toBeEmpty();

        $infolistSchema = BundleResource::infolist(new Schema);
        expect($infolistSchema->getComponents())->not->toBeEmpty();

        $pages = BundleResource::getPages();
        expect($pages)->toHaveKeys(['index', 'view', 'edit']);

        $query = BundleResource::getEloquentQuery();
        expect($query->count())->toBeGreaterThanOrEqual(0);
    }

    public function test_super_admin_can_render_bundle_view_page(): void
    {
        $user = User::create([
            'name' => 'Admin User 3',
            'email' => 'admin3.bundle@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'filter_label' => 'PC-2023-011 · Geotechnical Review',
            'filter_criteria' => ['project_code' => 'PC-2023-011'],
            'project_code' => 'PC-2023-011',
            'email_count' => 1,
            'notes' => 'Soil test analysis confirmed VO RM 120k.',
        ]);

        BundleEmail::create([
            'bundle_id' => $bundle->id,
            'message_id' => 'MSG_VIEW_01',
            'from_name' => 'Ir. Dr. Tan',
            'from_email' => 'dr.tan@geotech-consult.com',
            'subject' => 'Pier 4 VO Review',
            'snippet' => 'Estimation stands at RM 120k.',
            'received_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(ViewBundle::class, ['record' => $bundle->id])
            ->assertSee('PC-2023-011 · Geotechnical Review')
            ->assertSee('Ir. Dr. Tan')
            ->assertSee('Soil test analysis confirmed VO RM 120k.');
    }

    public function test_super_admin_can_render_bundle_edit_page(): void
    {
        $user = User::create([
            'name' => 'Admin User 4',
            'email' => 'admin4.bundle@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'filter_label' => 'Edit Bundle Test',
            'filter_criteria' => ['direct_only' => true],
            'project_code' => 'PC-2023-011',
            'email_count' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(EditBundle::class, ['record' => $bundle->id])
            ->assertSuccessful();
    }
}
