<x-filament-panels::page>
    <div class="space-y-8 max-w-5xl">
        <!-- Header Introduction Card -->
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Executive Sovereign Settings & Integrations</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage your private AI reasoning keys and Microsoft 365 / Outlook mailbox credentials. All sensitive API keys and secrets are encrypted at rest using AES-256 and isolated to your executive session.
            </p>
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
                            @if ($aiProbeStatus === 'success')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    🟢 Active ({{ $aiLatencyMs }}ms)
                                </span>
                            @endif
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
                                Primary reasoning model for executive briefings and Action Cards.
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
                                Automatic fallback provider invoked on Anthropic rate limits or timeouts.
                            </p>
                        </div>

                        <!-- AI Probe Diagnostic Display -->
                        @if ($aiProbeStatus === 'error')
                            <div class="p-3.5 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 space-y-2">
                                <div class="flex items-start space-x-2">
                                    <span class="text-red-600 dark:text-red-400 font-bold text-sm">❌</span>
                                    <div class="text-xs text-red-800 dark:text-red-200">
                                        <p class="font-semibold">{{ $aiProbeMessage }}</p>
                                        @if ($aiProbeRemediation)
                                            <p class="mt-1 text-[11px] text-red-700 dark:text-red-300">💡 <strong>How to fix:</strong> {{ $aiProbeRemediation }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                        <button
                            type="button"
                            wire:click="testAiConnection"
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors inline-flex items-center space-x-1.5"
                        >
                            <span wire:loading.remove wire:target="testAiConnection">⚡ Test AI Connection</span>
                            <span wire:loading wire:target="testAiConnection">Verifying...</span>
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
                                    🟢 Connected ({{ $outlookLatencyMs }}ms)
                                </span>
                            @elseif ($outlookProbeStatus === 'error')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                    🔴 Auth Error
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                    ⚪ Not Configured
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
                                    <span class="text-rose-600 dark:text-rose-400 font-bold text-sm">❌</span>
                                    <div class="text-xs text-rose-800 dark:text-rose-200">
                                        <p class="font-semibold">{{ $outlookProbeMessage }}</p>
                                        @if ($outlookProbeRemediation)
                                            <p class="mt-1 text-[11px] text-rose-700 dark:text-rose-300">💡 <strong>How to fix:</strong> {{ $outlookProbeRemediation }}</p>
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
                            <span wire:loading.remove wire:target="testOutlookConnection">📬 Test Outlook Connection</span>
                            <span wire:loading wire:target="testOutlookConnection">Connecting...</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Master Save Action Toolbar -->
            <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                    <p class="font-medium text-gray-700 dark:text-gray-200">🔐 Zero Data Leakage Boundary</p>
                    <p>Changes take effect immediately across your active Copilot drawer session without requiring server restarts.</p>
                </div>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="px-6 py-2.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 rounded-lg shadow-sm transition-colors focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 inline-flex items-center space-x-2"
                >
                    <span wire:loading.remove wire:target="save">Save All Settings</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
