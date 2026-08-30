<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('microsoft_client_id')->nullable()->after('gemini_api_key');
            $table->text('microsoft_client_secret')->nullable()->after('microsoft_client_id');
            $table->string('microsoft_tenant_id')->nullable()->after('microsoft_client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['microsoft_client_id', 'microsoft_client_secret', 'microsoft_tenant_id']);
        });
    }
};
