<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('openrouter_api_key')->nullable()->after('gemini_api_key');
            $table->string('favorite_model_1')->default('anthropic:claude-3-7-sonnet-20250219')->after('openrouter_api_key');
            $table->string('favorite_model_2')->default('openrouter:deepseek/deepseek-r1')->after('favorite_model_1');
            $table->string('favorite_model_3')->default('gemini:gemini-2.5-flash')->after('favorite_model_2');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'openrouter_api_key',
                'favorite_model_1',
                'favorite_model_2',
                'favorite_model_3',
            ]);
        });
    }
};
