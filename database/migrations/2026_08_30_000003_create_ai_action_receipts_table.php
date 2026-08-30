<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action_type')->index(); // outlook_draft, outlook_reply, outlook_forward, memory_commit, note_create, task_create
            $table->text('description');
            $table->json('target_recipients')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('executed'); // proposed, executed, rejected, failed
            $table->string('approval_token')->nullable()->index();
            $table->timestamp('executed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_receipts');
    }
};
