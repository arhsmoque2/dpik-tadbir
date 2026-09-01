<?php

namespace Tests\Feature\Services;

use App\Models\Bundle;
use App\Models\ProjectRegistryEntry;
use App\Models\User;
use App\Services\AutoPromotionService;
use App\Services\BundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleAutoPromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_service_auto_names_and_creates_bundles(): void
    {
        $user = User::create([
            'name' => 'Executive Tester',
            'email' => 'bundle.service@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        ProjectRegistryEntry::create([
            'project_code' => 'PC-2023-011',
            'project_name' => 'Geotechnical Review',
            'user_id' => $user->id,
            'summary' => 'Sungai Buloh Package 3',
        ]);

        $service = new BundleService;

        // Test project auto-naming
        $projectLabel = $service->determineFilterLabel(['project_code' => 'PC-2023-011'], 'PC-2023-011');
        expect($projectLabel)->toBe('PC-2023-011 · Geotechnical Review');

        // Test default auto-naming
        $defaultLabel = $service->determineFilterLabel(['direct_only' => true]);
        expect($defaultLabel)->toContain('Direct Correspondence');

        // Test creation
        $messages = [
            [
                'id' => 'MSG_001',
                'from_name' => 'Ir. Dr. Tan',
                'from_email' => 'dr.tan@geotech-consult.com',
                'subject' => 'Pier 4 VO Review',
                'snippet' => 'Estimation stands at RM 120k.',
            ],
        ];

        $bundle = $service->createBundle($user, ['project_code' => 'PC-2023-011'], $messages, 'PC-2023-011');

        expect($bundle->filter_label)->toBe('PC-2023-011 · Geotechnical Review')
            ->and($bundle->email_count)->toBe(1)
            ->and($bundle->bundleEmails)->toHaveCount(1);
    }

    public function test_auto_promotion_service_identifies_projects_with_three_or_more_retrievals(): void
    {
        $user = User::create([
            'name' => 'Executive Tester 2',
            'email' => 'autopromote@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        // Create 3 retrievals for PC-2023-011 in last 7 days
        for ($i = 1; $i <= 3; $i++) {
            Bundle::create([
                'user_id' => $user->id,
                'filter_label' => "Bundle #{$i}",
                'filter_criteria' => ['project_code' => 'PC-2023-011'],
                'project_code' => 'PC-2023-011',
                'retrieved_at' => now()->subDays(1),
                'email_count' => 2,
            ]);
        }

        // Create 2 retrievals for PC-2024-999 (below threshold)
        for ($j = 1; $j <= 2; $j++) {
            Bundle::create([
                'user_id' => $user->id,
                'filter_label' => "Bundle B #{$j}",
                'filter_criteria' => ['project_code' => 'PC-2024-999'],
                'project_code' => 'PC-2024-999',
                'retrieved_at' => now()->subDays(1),
                'email_count' => 1,
            ]);
        }

        $promotionService = new AutoPromotionService;
        $promoted = $promotionService->getPromotedProjects($user, 7, 3);

        expect($promoted)->toHaveCount(1)
            ->and($promoted[0]['project_code'])->toBe('PC-2023-011')
            ->and($promoted[0]['count'])->toBe(3);
    }
}
