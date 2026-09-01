<?php

namespace Tests\Feature\Livewire;

use App\Livewire\AiCopilotDrawer;
use App\Models\Bundle;
use App\Models\BundleEmail;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CopilotBundleScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_copilot_drawer_scopes_chat_session_to_bundle_id(): void
    {
        $user = User::create([
            'name' => 'Executive Bundle User',
            'email' => 'bundle.scoping@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'filter_label' => 'PC-2023-011 · Geotechnical Review',
            'filter_criteria' => ['project_code' => 'PC-2023-011'],
            'project_code' => 'PC-2023-011',
            'retrieved_at' => now(),
            'email_count' => 1,
            'notes' => 'Soil test analysis confirmed VO RM 120k.',
        ]);

        BundleEmail::create([
            'bundle_id' => $bundle->id,
            'message_id' => 'MSG_BUNDLE_01',
            'from_name' => 'Ir. Dr. Tan',
            'from_email' => 'dr.tan@geotech-consult.com',
            'subject' => 'Pier 4 VO Review',
            'snippet' => 'Estimation stands at RM 120k.',
            'received_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(AiCopilotDrawer::class)
            ->dispatch('open-copilot-drawer', bundleId: $bundle->id);

        $session = ChatSession::where('user_id', $user->id)->first();
        expect($session)->not->toBeNull()
            ->and($session->bundle_id)->toBe($bundle->id);
    }
}
