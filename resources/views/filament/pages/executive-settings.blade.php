<x-filament-panels::page>
    <div class="space-y-8 max-w-5xl">
        <!-- Header Introduction Card & System Health Bar -->
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Executive Sovereign Settings & Health Diagnostics</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Manage private AI keys, DPIK Exabytes IMAP/SMTP mailbox credentials, and multi-model catalogs. Sensitive keys and passwords are AES-256 encrypted at rest.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="testAllConnections"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-xs font-bold text-amber-900 dark:text-amber-100 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/50 dark:hover:bg-amber-900/70 rounded-lg transition-colors inline-flex items-center space-x-2 shrink-0 self-start md:self-auto"
                >
                    <span wire:loading.remove wire:target="testAllConnections">⚡ Run Full System Health Check</span>
                    <span wire:loading wire:target="testAllConnections">Probing All Services...</span>
                </button>
            </div>

            <!-- At-A-Glance Live Service Badges -->
            <div class="pt-3 border-t border-gray-100 dark:border-gray-700 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5">
                <!-- AI Direct -->
                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 text-center space-y-1">
                    <div class="text-[10px] uppercase font-bold text-gray-500 dark:text-gray-400">AI Direct</div>
                    @if ($aiProbeStatus === 'success')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span> Active ({{ $aiLatencyMs }}ms)
                        </span>
                    @elseif ($aiProbeStatus === 'error')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1"></span> Error
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Unchecked
                        </span>
                    @endif
                </div>

                <!-- OpenRouter -->
                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 text-center space-y-1">
                    <div class="text-[10px] uppercase font-bold text-gray-500 dark:text-gray-400">OpenRouter</div>
                    @if ($openrouterProbeStatus === 'success')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span> Active ({{ $openrouterLatencyMs }}ms)
                        </span>
                    @elseif ($openrouterProbeStatus === 'error')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1"></span> Error
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Unchecked
                        </span>
                    @endif
                </div>

                <!-- IMAP Mailbox -->
                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 text-center space-y-1">
                    <div class="text-[10px] uppercase font-bold text-gray-500 dark:text-gray-400">IMAP (993)</div>
                    @if ($imapProbeStatus === 'success')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span> Connected ({{ $imapLatencyMs }}ms)
                        </span>
                    @elseif ($imapProbeStatus === 'error')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1"></span> Failed
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Unchecked
                        </span>
                    @endif
                </div>

                <!-- SMTP Outgoing -->
                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 text-center space-y-1">
                    <div class="text-[10px] uppercase font-bold text-gray-500 dark:text-gray-400">SMTP (465)</div>
                    @if ($smtpProbeStatus === 'success')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span> Ready ({{ $smtpLatencyMs }}ms)
                        </span>
                    @elseif ($smtpProbeStatus === 'error')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1"></span> Failed
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Unchecked
                        </span>
                    @endif
                </div>

                <!-- M365 Outlook -->
                <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 text-center space-y-1 col-span-2 sm:col-span-1">
                    <div class="text-[10px] uppercase font-bold text-gray-500 dark:text-gray-400">Outlook M365</div>
                    @if ($outlookProbeStatus === 'success')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span> Active ({{ $outlookLatencyMs }}ms)
                        </span>
                    @elseif ($outlookProbeStatus === 'error')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1"></span> Error
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Optional
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <form wire:submit="save" class="space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Section 1: AI Provider Configuration -->
                <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col justify-between space-y-6">
                    <div class="space-y-5">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                            <div class="flex items-center space-x-2.5">
                                <span class="p-2 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 font-semibold text-xs">AI</span>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">AI Model & Provider Keys</h3>
                            </div>
                        </div>

                        <!-- Anthropic Key -->
                        <div class="space-y-1.5">
                            <label for="anthropic_api_key" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                Anthropic API Key (Claude 3.7 Sonnet)
                            </label>
                            <input
                                type="password"
                                id="anthropic_api_key"
                                wire:model="anthropic_api_key"
                                placeholder="sk-ant-api03-..."
                                autocomplete="off"
                                class="w-full px-3.5 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white font-mono"
                            />
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                Primary direct reasoning model for executive briefings and Action Cards.
                            </p>
                        </div>

                        <!-- Gemini Key -->
                        <div class="space-y-1.5">
                            <label for="gemini_api_key" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                Google Gemini API Key (Gemini 2.5 Flash)
                            </label>
                            <input
                                type="password"
                                id="gemini_api_key"
                                wire:model="gemini_api_key"
                                placeholder="AIzaSy..."
                                autocomplete="off"
                                class="w-full px-3.5 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white font-mono"
                            />
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                High-speed direct provider and automated fallback on rate limits.
                            </p>
                        </div>

                        <!-- OpenRouter Key -->
                        <div class="space-y-1.5">
                            <label for="openrouter_api_key" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                OpenRouter API Key (Unified Multi-Model Catalog)
                            </label>
                            <input
                                type="password"
                                id="openrouter_api_key"
                                wire:model="openrouter_api_key"
                                placeholder="sk-or-v1-..."
                                autocomplete="off"
                                class="w-full px-3.5 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white font-mono"
                            />
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                Unified sovereign gateway for DeepSeek R1, Claude, GPT-4o, and Llama 3.3.
                            </p>
                        </div>

                        <!-- AI Probe Diagnostic Display -->
                        @if ($aiProbeStatus === 'error')
                            <div class="p-3.5 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 space-y-2">
                                <div class="flex items-start space-x-2">
                                    <span class="text-red-600 dark:text-red-400 font-bold text-sm">!</span>
                                    <div class="text-xs text-red-800 dark:text-red-200">
                                        <p class="font-semibold">{{ $aiProbeMessage }}</p>
                                        @if ($aiProbeRemediation)
                                            <p class="mt-1 text-[11px] text-red-700 dark:text-red-300"><strong>How to fix:</strong> {{ $aiProbeRemediation }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($openrouterProbeStatus === 'error')
                            <div class="p-3.5 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 space-y-2">
                                <div class="flex items-start space-x-2">
                                    <span class="text-red-600 dark:text-red-400 font-bold text-sm">!</span>
                                    <div class="text-xs text-red-800 dark:text-red-200">
                                        <p class="font-semibold">{{ $openrouterProbeMessage }}</p>
                                        @if ($openrouterProbeRemediation)
                                            <p class="mt-1 text-[11px] text-red-700 dark:text-red-300"><strong>How to fix:</strong> {{ $openrouterProbeRemediation }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- AI Key Action Buttons -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            wire:click="testOpenRouterConnection"
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors inline-flex items-center space-x-1.5"
                        >
                            <span wire:loading.remove wire:target="testOpenRouterConnection">Test OpenRouter</span>
                            <span wire:loading wire:target="testOpenRouterConnection">Verifying...</span>
                        </button>
                        <button
                            type="button"
                            wire:click="testAiConnection"
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors inline-flex items-center space-x-1.5"
                        >
                            <span wire:loading.remove wire:target="testAiConnection">Test AI Keys</span>
                            <span wire:loading wire:target="testAiConnection">Verifying...</span>
                        </button>
                    </div>
                </div>

                <!-- Section 2: DPIK Corporate IMAP / Exabytes Mailbox -->
                <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col justify-between space-y-6">
                    <div class="space-y-5">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                            <div class="flex items-center space-x-2.5">
                                <span class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 font-semibold text-xs">MAIL</span>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">DPIK IMAP / SMTP Mailbox</h3>
                            </div>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400 font-mono">mail.dpik.com.my</span>
                        </div>

                        <!-- IMAP Username & Password -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label for="imap_username" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    Email Account
                                </label>
                                <input
                                    type="email"
                                    id="imap_username"
                                    wire:model="imap_username"
                                    placeholder="rahman@dpik.com.my"
                                    class="w-full px-3.5 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-emerald-500 text-gray-900 dark:text-white"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <label for="imap_password" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    Mailbox Password
                                </label>
                                <input
                                    type="password"
                                    id="imap_password"
                                    wire:model="imap_password"
                                    placeholder="••••••••••••"
                                    autocomplete="off"
                                    class="w-full px-3.5 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-emerald-500 text-gray-900 dark:text-white font-mono"
                                />
                            </div>
                        </div>

                        <!-- Server Host & Ports -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1.5 col-span-1">
                                <label for="imap_host" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    Mail Host
                                </label>
                                <input
                                    type="text"
                                    id="imap_host"
                                    wire:model="imap_host"
                                    placeholder="mail.dpik.com.my"
                                    class="w-full px-3 py-1.5 text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white font-mono"
                                />
                            </div>
                            <div class="space-y-1.5 col-span-1">
                                <label for="imap_port" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    IMAP Port
                                </label>
                                <input
                                    type="number"
                                    id="imap_port"
                                    wire:model="imap_port"
                                    placeholder="993"
                                    class="w-full px-3 py-1.5 text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white font-mono"
                                />
                            </div>
                            <div class="space-y-1.5 col-span-1">
                                <label for="smtp_port" class="block text-[11px] font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    SMTP Port
                                </label>
                                <input
                                    type="number"
                                    id="smtp_port"
                                    wire:model="smtp_port"
                                    placeholder="465"
                                    class="w-full px-3 py-1.5 text-xs bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white font-mono"
                                />
                            </div>
                        </div>

                        <!-- Diagnostic Message -->
                        @if ($imapProbeStatus === 'error')
                            <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-200">
                                {{ $imapProbeMessage }}
                            </div>
                        @elseif ($imapProbeStatus === 'success')
                            <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-200">
                                {{ $imapProbeMessage }} ({{ $imapLatencyMs }}ms)
                            </div>
                        @endif
                    </div>

                    <!-- IMAP & SMTP Action Buttons -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            wire:click="testImapConnection"
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:hover:bg-emerald-900/50 rounded-lg transition-colors inline-flex items-center space-x-1.5"
                        >
                            <span wire:loading.remove wire:target="testImapConnection">Test IMAP (993)</span>
                            <span wire:loading wire:target="testImapConnection">Checking...</span>
                        </button>
                        <button
                            type="button"
                            wire:click="testSmtpConnection"
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors inline-flex items-center space-x-1.5"
                        >
                            <span wire:loading.remove wire:target="testSmtpConnection">Test SMTP (465)</span>
                            <span wire:loading wire:target="testSmtpConnection">Checking...</span>
                        </button>
                    </div>
                </div>

                <!-- Section 2: Microsoft 365 & Outlook MCP Integration -->
                <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col justify-between space-y-6">
                    <div class="space-y-5">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                            <div class="flex items-center space-x-2.5">
                                <span class="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-semibold text-xs">M365</span>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Microsoft 365 & Outlook</h3>
                            </div>
                            @if ($outlookProbeStatus === 'success')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                    Connected ({{ $outlookLatencyMs }}ms)
                                </span>
                            @elseif ($outlookProbeStatus === 'error')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></span>
                                    Auth Error
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1.5"></span>
                                    Not Configured
                                </span>
                            @endif
                        </div>

                        <!-- Client ID -->
                        <div class="space-y-1.5">
                            <label for="microsoft_client_id" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                Application (Client) ID
                            </label>
                            <input
                                type="text"
                                id="microsoft_client_id"
                                wire:model="microsoft_client_id"
                                placeholder="00000000-0000-0000-0000-000000000000"
                                class="w-full px-3.5 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 dark:text-white font-mono"
                            />
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                Azure Entra ID Application ID for Microsoft Graph.
                            </p>
                        </div>

                        <!-- Client Secret -->
                        <div class="space-y-1.5">
                            <label for="microsoft_client_secret" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                Client Secret Value
                            </label>
                            <input
                                type="password"
                                id="microsoft_client_secret"
                                wire:model="microsoft_client_secret"
                                placeholder="********************"
                                autocomplete="off"
                                class="w-full px-3.5 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 dark:text-white font-mono"
                            />
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                OAuth Client Secret value. Stored encrypted at rest.
                            </p>
                        </div>

                        <!-- Tenant ID -->
                        <div class="space-y-1.5">
                            <label for="microsoft_tenant_id" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                Directory (Tenant) ID
                            </label>
                            <input
                                type="text"
                                id="microsoft_tenant_id"
                                wire:model="microsoft_tenant_id"
                                placeholder="00000000-0000-0000-0000-000000000000"
                                class="w-full px-3.5 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 dark:text-white font-mono"
                            />
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                DPIK Microsoft 365 Tenant ID or "organizations".
                            </p>
                        </div>

                        <!-- Outlook Diagnostic Error Card -->
                        @if ($outlookProbeStatus === 'error')
                            <div class="p-3.5 rounded-lg bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 space-y-2">
                                <div class="flex items-start space-x-2">
                                    <span class="text-rose-600 dark:text-rose-400 font-bold text-sm">!</span>
                                    <div class="text-xs text-rose-800 dark:text-rose-200">
                                        <p class="font-semibold">{{ $outlookProbeMessage }}</p>
                                        @if ($outlookProbeRemediation)
                                            <p class="mt-1 text-[11px] text-rose-700 dark:text-rose-300"><strong>How to fix:</strong> {{ $outlookProbeRemediation }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                        <button
                            type="button"
                            wire:click="testOutlookConnection"
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors inline-flex items-center space-x-1.5"
                        >
                            <span wire:loading.remove wire:target="testOutlookConnection">Test Outlook Connection</span>
                            <span wire:loading wire:target="testOutlookConnection">Connecting...</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Section 3: Top-3 In-Chat Favorite Models (ADR-018) -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <div class="flex items-center space-x-2.5">
                        <span class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs">TOP-3</span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Top-3 Favorite Models (Copilot Hot-Swapper)</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Governed by ADR-018. Configure your 3 preferred models for instantaneous, zero-reload switching in the AI Copilot Drawer header (<kbd class="px-1 py-0.5 text-[10px] font-mono bg-gray-100 dark:bg-gray-700 rounded">Cmd+J</kbd>).
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Slot 1 -->
                    <div class="space-y-1.5">
                        <label for="favorite_model_1" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 flex items-center justify-between">
                            <span>Favorite Slot 1 (Default)</span>
                            <span class="text-[10px] text-amber-600 dark:text-amber-400 font-normal">Primary Reasoning</span>
                        </label>
                        <select
                            id="favorite_model_1"
                            wire:model="favorite_model_1"
                            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white"
                        >
                            @foreach ($this->getAvailableModelOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            Invoked by default for new executive chat sessions.
                        </p>
                    </div>

                    <!-- Slot 2 -->
                    <div class="space-y-1.5">
                        <label for="favorite_model_2" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 flex items-center justify-between">
                            <span>Favorite Slot 2</span>
                            <span class="text-[10px] text-blue-600 dark:text-blue-400 font-normal">Logic & Math</span>
                        </label>
                        <select
                            id="favorite_model_2"
                            wire:model="favorite_model_2"
                            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white"
                        >
                            @foreach ($this->getAvailableModelOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            Optimal for deep calculations, engineering audits, and reasoning.
                        </p>
                    </div>

                    <!-- Slot 3 -->
                    <div class="space-y-1.5">
                        <label for="favorite_model_3" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 flex items-center justify-between">
                            <span>Favorite Slot 3</span>
                            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-normal">Speed & Batch</span>
                        </label>
                        <select
                            id="favorite_model_3"
                            wire:model="favorite_model_3"
                            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white"
                        >
                            @foreach ($this->getAvailableModelOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            Optimal for rapid summaries, inbox scans, and triage.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Section 4: Adaptive Bottom Navigation Customization (ADR-022) -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3">
                    <div class="flex items-center space-x-2.5">
                        <span class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs">NAV</span>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Adaptive Floating Navigation Slots</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Governed by ADR-022. Configure up to 4 quick-access destinations flanking the elevated center AI Copilot FAB button.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Nav Slot 1 -->
                    <div class="space-y-1.5">
                        <label for="bottom_nav_slot_1" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            Slot 1 (Left 1)
                        </label>
                        <select
                            id="bottom_nav_slot_1"
                            wire:model="bottom_nav_slot_1"
                            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white"
                        >
                            @foreach ($this->getAvailableBottomNavOptions() as $key => $opt)
                                <option value="{{ $key }}">{{ $opt['label'] }} ({{ $opt['url'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Nav Slot 2 -->
                    <div class="space-y-1.5">
                        <label for="bottom_nav_slot_2" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            Slot 2 (Left 2)
                        </label>
                        <select
                            id="bottom_nav_slot_2"
                            wire:model="bottom_nav_slot_2"
                            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white"
                        >
                            @foreach ($this->getAvailableBottomNavOptions() as $key => $opt)
                                <option value="{{ $key }}">{{ $opt['label'] }} ({{ $opt['url'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Nav Slot 3 -->
                    <div class="space-y-1.5">
                        <label for="bottom_nav_slot_3" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            Slot 3 (Right 1)
                        </label>
                        <select
                            id="bottom_nav_slot_3"
                            wire:model="bottom_nav_slot_3"
                            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white"
                        >
                            @foreach ($this->getAvailableBottomNavOptions() as $key => $opt)
                                <option value="{{ $key }}">{{ $opt['label'] }} ({{ $opt['url'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Nav Slot 4 -->
                    <div class="space-y-1.5">
                        <label for="bottom_nav_slot_4" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            Slot 4 (Right 2)
                        </label>
                        <select
                            id="bottom_nav_slot_4"
                            wire:model="bottom_nav_slot_4"
                            class="w-full px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white"
                        >
                            @foreach ($this->getAvailableBottomNavOptions() as $key => $opt)
                                <option value="{{ $key }}">{{ $opt['label'] }} ({{ $opt['url'] }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Master Save Action Toolbar -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                    <p class="font-medium text-gray-700 dark:text-gray-200">Zero Data Leakage Boundary</p>
                    <p>Changes take effect immediately across your active Copilot drawer session without requiring server restarts.</p>
                </div>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 rounded-lg shadow-sm transition-colors focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 inline-flex items-center space-x-2"
                >
                    <span wire:loading.remove wire:target="save">Save Personal Settings</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>

        @if (auth()->user()?->role === 'super_admin' || in_array(auth()->user()?->email, ['smoque@gmail.com', 'arh.homelab@gmail.com', 'rahman@dpik.com.my'], true))
            <!-- Section 3: Super Admin AI & MCP JSON Control Plane -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-amber-200 dark:border-amber-800/60 space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-4">
                    <div class="space-y-1">
                        <div class="flex items-center space-x-2">
                            <span class="p-1.5 rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold text-xs uppercase tracking-wider">Super Admin</span>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">AI & MCP Control Plane (<code class="text-xs text-amber-600 dark:text-amber-400">ai-configuration.json</code>)</h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Fine-tune the global System Prompt template, Anti-Hallucination Rules, Context Mode Token Budgets, Memory RRF thresholds, and MCP tool states. Updates hot-reload instantly across all active executive sessions.
                        </p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button
                            type="button"
                            wire:click="formatAiConfigJson"
                            class="px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors"
                        >
                            Format JSON
                        </button>
                        <button
                            type="button"
                            wire:click="resetAiConfiguration"
                            wire:confirm="Are you sure you want to reset all AI prompts, rules, and MCP configurations back to factory defaults?"
                            class="px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/50 rounded-lg transition-colors"
                        >
                            Reset to Defaults
                        </button>
                    </div>
                </div>

                @if ($configError)
                    <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/40 border border-red-200 dark:border-red-800 text-xs text-red-800 dark:text-red-200 font-mono">
                        {{ $configError }}
                    </div>
                @endif

                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Placeholders supported: <code class="text-amber-600 dark:text-amber-400">{executive_name}</code>, <code class="text-amber-600 dark:text-amber-400">{date}</code>, <code class="text-amber-600 dark:text-amber-400">{tools}</code>, <code class="text-amber-600 dark:text-amber-400">{personalization}</code>, <code class="text-amber-600 dark:text-amber-400">{bundle}</code>, <code class="text-amber-600 dark:text-amber-400">{memory}</code></span>
                        <span>Hot-reloaded via Cache</span>
                    </div>

                    <textarea
                        wire:model="rawAiConfigJson"
                        rows="22"
                        spellcheck="false"
                        class="w-full p-4 text-xs font-mono bg-gray-900 text-gray-100 border border-gray-700 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-transparent leading-relaxed"
                    ></textarea>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Tip: You can export your session database using <code class="text-gray-700 dark:text-gray-300 font-mono">php artisan session:export</code> or via <a href="/admin/sessions/export/db" class="text-amber-600 hover:underline">/admin/sessions/export/db</a>.
                    </p>
                    <button
                        type="button"
                        wire:click="saveAiConfiguration"
                        wire:loading.attr="disabled"
                        class="px-6 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-lg shadow-sm transition-colors focus:ring-2 focus:ring-emerald-500 inline-flex items-center space-x-2"
                    >
                        <span wire:loading.remove wire:target="saveAiConfiguration">Save AI Configuration</span>
                        <span wire:loading wire:target="saveAiConfiguration">Saving...</span>
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
