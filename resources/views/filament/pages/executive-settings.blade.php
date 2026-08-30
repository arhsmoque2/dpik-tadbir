<x-filament-panels::page>
    <div class="space-y-8 max-w-5xl">
        <!-- Header Introduction Card -->
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Executive Sovereign Settings & Integrations</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage your private AI reasoning keys, OpenRouter multi-model catalog, and Microsoft 365 / Outlook mailbox credentials. All sensitive API keys and secrets are encrypted at rest using AES-256 and isolated to your executive session.
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
                            <div class="flex items-center space-x-2">
                                @if ($aiProbeStatus === 'success')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                        AI Active ({{ $aiLatencyMs }}ms)
                                    </span>
                                @endif
                                @if ($openrouterProbeStatus === 'success')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                        OpenRouter ({{ $openrouterLatencyMs }}ms)
                                    </span>
                                @endif
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

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-end space-x-2.5">
                        <button
                            type="button"
                            wire:click="testOpenRouterConnection"
                            wire:loading.attr="disabled"
                            class="px-3 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition-colors inline-flex items-center space-x-1.5"
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
                    <span wire:loading.remove wire:target="save">Save All Settings</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
