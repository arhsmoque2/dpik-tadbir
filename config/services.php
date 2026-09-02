<?php

return [
    'ai' => [
        'default_provider' => env('AI_DEFAULT_PROVIDER', 'anthropic'),
        'default_model' => env('AI_DEFAULT_MODEL', 'claude-3-7-sonnet-20250219'),
        'fallback_provider' => env('AI_FALLBACK_PROVIDER', 'gemini'),
        'fallback_model' => env('AI_FALLBACK_MODEL', 'gemini-2.5-flash'),
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'openrouter_api_key' => env('OPENROUTER_API_KEY'),
        'openai_api_key' => env('OPENAI_API_KEY'),
    ],

    'company_mail' => [
        'host' => env('COMPANY_MAIL_HOST', 'mail.dpik.com.my'),
        'imap_port' => (int) env('COMPANY_MAIL_IMAP_PORT', 993),
        'smtp_port' => (int) env('COMPANY_MAIL_SMTP_PORT', 465),
        'timeout' => (int) env('COMPANY_MAIL_TIMEOUT', 30),
        'drafts_folder' => env('COMPANY_MAIL_DRAFTS_FOLDER', 'INBOX.Drafts'),
    ],

    'registration' => [
        'allowed_emails' => array_filter(array_map('trim', explode(',', (string) env('ALLOWED_REGISTRATION_EMAILS', 'rahman@dpik.com.my,smoque@gmail.com,arh.homelab@gmail.com,hilmio@dpik.com.my,hamid@dpik.com.my')))),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost:8000').'/auth/google/callback'),
    ],
];
