<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the Microsoft Graph / Entra credential columns added by
 * 2026_08_30_000010. The Outlook MCP (Graph API via a Python subprocess)
 * bridge never had a real backing package to run — see issue #40 — and has
 * been replaced entirely by MailBridge, which talks IMAP/SMTP directly to
 * the company mail server (mail.dpik.com.my) using each executive's own
 * mailbox credentials (users.imap_* / smtp_*). No Entra app registration
 * is required any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['microsoft_client_id', 'microsoft_client_secret', 'microsoft_tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('microsoft_client_id')->nullable()->after('gemini_api_key');
            $table->text('microsoft_client_secret')->nullable()->after('microsoft_client_id');
            $table->string('microsoft_tenant_id')->nullable()->after('microsoft_client_secret');
        });
    }
};
