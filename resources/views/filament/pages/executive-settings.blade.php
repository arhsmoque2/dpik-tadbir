<x-filament-panels::page>
    <div class="space-y-6 max-w-4xl">
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Personal AI Model & Provider Configuration</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Configure your sovereign Anthropic and Google Gemini API keys below. If keys are left blank, Tadbir will automatically fall back to the central corporate environment / SOPS credentials.
            </p>
        </div>

        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-6">
            <form wire:submit="save" class="space-y-6">
                <!-- Anthropic Key -->
                <div class="space-y-2">
                    <label for="anthropic_api_key" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Anthropic API Key (Claude 3.7 Sonnet)
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="anthropic_api_key"
                            wire:model="anthropic_api_key"
                            placeholder="sk-ant-api03-..."
                            autocomplete="off"
                            class="w-full px-4 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white font-mono"
                        />
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Primary model provider for executive email synthesis, reasoning turns, and action cards. Stored encrypted at rest with your application master key.
                    </p>
                </div>

                <!-- Gemini Key -->
                <div class="space-y-2">
                    <label for="gemini_api_key" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Google Gemini API Key (Gemini 2.5 Flash)
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="gemini_api_key"
                            wire:model="gemini_api_key"
                            placeholder="AIzaSy..."
                            autocomplete="off"
                            class="w-full px-4 py-2 text-sm bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent text-gray-900 dark:text-white font-mono"
                        />
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Secondary fallback model provider activated automatically if Anthropic encounters rate limits or upstream timeouts. Stored encrypted at rest.
                    </p>
                </div>

                <!-- Save Action Button -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Keys are isolated to your executive session.
                    </span>
                    <button
                        type="submit"
                        class="px-5 py-2.5 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 rounded-lg shadow-sm transition-colors focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                    >
                        Save API Keys
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
