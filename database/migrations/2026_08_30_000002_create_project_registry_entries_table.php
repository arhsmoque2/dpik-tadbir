<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_registry_entries', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->index();
            $table->string('project_name')->nullable();
            $table->text('summary');
            $table->json('decisions')->nullable();
            $table->json('commitments')->nullable();
            $table->string('source_type')->default('email_summary'); // email_summary, meeting_note, manual_entry
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['project_code', 'recorded_at']);
        });

        // If SQLite is used, create virtual table for FTS5 full-text search
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE VIRTUAL TABLE IF NOT EXISTS project_registry_fts USING fts5(
                    project_code,
                    project_name,
                    summary,
                    content='project_registry_entries',
                    content_rowid='id'
                )
            ");

            DB::statement("
                CREATE TRIGGER IF NOT EXISTS project_registry_ai AFTER INSERT ON project_registry_entries BEGIN
                    INSERT INTO project_registry_fts(rowid, project_code, project_name, summary)
                    VALUES (new.id, new.project_code, coalesce(new.project_name, ''), new.summary);
                END;
            ");

            DB::statement("
                CREATE TRIGGER IF NOT EXISTS project_registry_ad AFTER DELETE ON project_registry_entries BEGIN
                    INSERT INTO project_registry_fts(project_registry_fts, rowid, project_code, project_name, summary)
                    VALUES ('delete', old.id, old.project_code, coalesce(old.project_name, ''), old.summary);
                END;
            ");

            DB::statement("
                CREATE TRIGGER IF NOT EXISTS project_registry_au AFTER UPDATE ON project_registry_entries BEGIN
                    INSERT INTO project_registry_fts(project_registry_fts, rowid, project_code, project_name, summary)
                    VALUES ('delete', old.id, old.project_code, coalesce(old.project_name, ''), old.summary);
                    INSERT INTO project_registry_fts(rowid, project_code, project_name, summary)
                    VALUES (new.id, new.project_code, coalesce(new.project_name, ''), new.summary);
                END;
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS project_registry_ai');
            DB::statement('DROP TRIGGER IF EXISTS project_registry_ad');
            DB::statement('DROP TRIGGER IF EXISTS project_registry_au');
            DB::statement('DROP TABLE IF EXISTS project_registry_fts');
        }

        Schema::dropIfExists('project_registry_entries');
    }
};
