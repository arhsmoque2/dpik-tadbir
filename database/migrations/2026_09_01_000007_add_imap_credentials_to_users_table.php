<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('imap_host')->nullable()->after('microsoft_tenant_id');
            $table->unsignedSmallInteger('imap_port')->nullable()->after('imap_host');
            $table->string('imap_username')->nullable()->after('imap_port');
            $table->text('imap_password')->nullable()->after('imap_username');
            $table->string('smtp_host')->nullable()->after('imap_password');
            $table->unsignedSmallInteger('smtp_port')->nullable()->after('smtp_host');
            $table->text('smtp_password')->nullable()->after('smtp_port');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'imap_host',
                'imap_port',
                'imap_username',
                'imap_password',
                'smtp_host',
                'smtp_port',
                'smtp_password',
            ]);
        });
    }
};
