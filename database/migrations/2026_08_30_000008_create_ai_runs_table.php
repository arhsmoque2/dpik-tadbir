<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('chat_session_id')->nullable()->constrained('chat_sessions')->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('model')->index();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0.000000);
            $table->decimal('cost_myr', 10, 6)->default(0.000000);
            $table->boolean('has_pii')->default(false);
            $table->string('status')->default('completed'); // completed, suspended, failed
            $table->longText('payload')->nullable();
            $table->longText('response')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
