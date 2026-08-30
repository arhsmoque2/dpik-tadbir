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
        // Guard against execution in production environments
        if (app()->environment('production')) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@dpik.com.my'],
            [
                'name' => 'Admin DPIK',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
            ]
        );

        AllowedRegistrationEmail::firstOrCreate(
            ['email' => 'admin@dpik.com.my'],
            [
                'notes' => 'Primary administrative executive account for E2E testing',
                'created_by_user_id' => $admin->id,
            ]
        );

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

        ExecutivePreset::firstOrCreate(
            ['title' => 'Tender Review Brief', 'user_id' => $admin->id],
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
