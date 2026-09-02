<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_notes', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('title')->index();
        });
    }

    public function down(): void
    {
        Schema::table('personal_notes', function (Blueprint $table) {
            $table->dropColumn('is_pinned');
        });
    }
};
