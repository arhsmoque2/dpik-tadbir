<?php

namespace Database\Seeders;

use App\Models\AllowedRegistrationEmail;
use App\Models\ExecutivePreset;
use App\Models\ProjectRegistryEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with deterministic fixtures for testing and local dev.
     */
    public function run(): void
    {
        // All seeder queries use idempotent firstOrCreate fixtures

        $whitelistEntries = [
            [
                'email' => 'rahman@dpik.com.my',
                'notes' => 'Super Admin (Owner)',
                'role' => 'super_admin',
            ],
            [
                'email' => 'smoque@gmail.com',
                'notes' => 'Super Admin (Owner)',
                'role' => 'super_admin',
            ],
            [
                'email' => 'arh.homelab@gmail.com',
                'notes' => 'Super Admin (Owner)',
                'role' => 'super_admin',
            ],
            [
                'email' => 'hilmio@dpik.com.my',
                'notes' => 'Managing Director',
                'role' => 'executive',
            ],
            [
                'email' => 'hamid@dpik.com.my',
                'notes' => 'Corporate Administrator',
                'role' => 'executive',
            ],
            [
                'email' => 'admin@dpik.com.my',
                'notes' => 'Primary administrative executive account for testing',
                'role' => 'super_admin',
            ],
        ];

        $admin = null;
        foreach ($whitelistEntries as $entry) {
            $user = User::firstOrCreate(
                ['email' => $entry['email']],
                [
                    'password' => Hash::make('password'),
                    'role' => $entry['role'],
                ]
            );

            AllowedRegistrationEmail::firstOrCreate(
                ['email' => $entry['email']],
                [
                    'notes' => $entry['notes'],
                    'created_by_user_id' => $user->id,
                ]
            );

            if ($entry['email'] === 'admin@dpik.com.my') {
                $admin = $user;
            }
        }

        ProjectRegistryEntry::firstOrCreate(
            ['project_code' => 'PC-2023-011'],
            [
                'project_name' => 'Jambatan Sungai Udang',
                'summary' => 'Projek pembinaan jambatan konkrit pratuang 4 lorong merentasi Sungai Udang.',
                'decisions' => ['Kelulusan reka bentuk terperinci disahkan oleh JKR Sarawak'],
                'commitments' => ['Penyerahan laporan interim claim 4 dijadualkan sebelum akhir bulan'],
                'source_type' => 'tender_brief',
                'user_id' => $admin->id,
                'recorded_at' => now(),
            ]
        );

        // Seeded as a system-wide default (user_id null) rather than scoped to
        // $admin's id. AutoLoginBypassMiddleware logs in as the first
        // super_admin by seed/id order (rahman@dpik.com.my), not necessarily
        // admin@dpik.com.my — a user-scoped seed silently produced an empty
        // presets ribbon for whichever executive the bypass actually resolves
        // to. Both AiCopilotDrawer::presets() and
        // ExecutivePresetResource::getEloquentQuery() already scope via
        // `where('user_id', auth()->id())->orWhereNull('user_id')`, so a null
        // owner is visible to every executive regardless of which account is
        // active — the correct semantics for a system-seeded demo preset
        // anyway, not just a workaround.
        ExecutivePreset::firstOrCreate(
            ['title' => 'Tender Review Brief', 'user_id' => null],
            [
                'description' => 'Generate executive summary of active project tender documents',
                'prompt_template' => 'Sila semak dokumen tender bagi projek {project_code} dan rumuskan risiko utama.',
                'category' => 'review',
                'icon' => 'heroicon-o-document-text',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }
}
