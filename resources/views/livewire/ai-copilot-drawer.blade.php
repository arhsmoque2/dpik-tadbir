<div
    x-data="{
        isOpen: @entangle('isOpen'),
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'j') {
                    e.preventDefault();
                    $wire.toggleDrawer();
                }
            });
        }
    }"
    @toggle-copilot-drawer.window="isOpen = !isOpen"
    x-cloak
>
    <!-- Drawer Overlay -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40"
        @click="$wire.closeDrawer()"
        aria-hidden="true"
    ></div>

    <!-- Slide-over Drawer Panel -->
    <div
        x-show="isOpen"
        x-transition:enter="transform transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        data-copilot-drawer
        class="fixed inset-y-0 right-0 z-50 w-full max-w-lg md:max-w-xl bg-[#111215] text-[#F3F4F6] border-l border-[#2C2F38] shadow-2xl flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-label="Executive AI Copilot Drawer"
    >
        <!-- Header -->
        <div class="px-5 py-4 border-b border-[#2C2F38] bg-[#18191E] flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-[#C9A36D]/15 border border-[#C9A36D]/30 flex items-center justify-center text-[#C9A36D] shrink-0">
                    <svg style="width: 20px; height: 20px; min-width: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold tracking-wide text-white flex items-center space-x-2">
                        <span>DPIK Copilot</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-mono font-normal tracking-normal {{ $outlookStatus === 'online' ? 'bg-[#429A6A]/20 text-[#429A6A] border border-[#429A6A]/30' : 'bg-zinc-800 text-zinc-400 border border-zinc-700' }}">
                            {{ $outlookStatus === 'online' ? 'Outlook Graph Connected' : 'Outlook Offline' }}
                        </span>
                    </h3>
                    <p class="text-xs text-[#9CA3AF]">Zero-raw-storage enterprise memory & action dispatcher</p>
                </div>
            </div>

            <div class="flex items-center space-x-2.5">
                <!-- Two-Tier Model Selector (ADR-018 / UI-14) -->
                <div class="relative" x-data="{ open: @entangle('isModelSwapperOpen') }">
                    <button
                        type="button"
                        @click="open = !open"
                        class="inline-flex items-center space-x-1.5 px-2.5 py-1 text-xs rounded-full bg-[#18191E] hover:bg-[#21232B] text-zinc-300 hover:text-white border border-[#2C2F38] hover:border-[#C9A36D]/40 transition-all font-mono"
                        title="Runtime Model Swapper (ADR-018)"
                    >
                        <svg style="width: 14px; height: 14px; min-width: 14px; color: #C9A36D;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m-2 6h2m14-6h2m-2 6h2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                        </svg>
                        <span class="truncate max-w-[170px]">{{ $this->getActiveModelBadgeLabel() }}</span>
                        <svg style="width: 12px; height: 12px; min-width: 12px; color: #A1A1AA;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Ephemeral 3-Favorites Quick-Switcher Popover -->
                    <div
                        x-show="open"
                        @click.outside="open = false"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="absolute right-0 mt-2 w-72 bg-[#18191E] border border-[#2C2F38] rounded-xl shadow-2xl z-50 p-2 space-y-1"
                        style="display: none;"
                    >
                        <div class="px-2 py-1.5 border-b border-[#2C2F38] flex items-center justify-between">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400">Top-3 Favorites</span>
                            <span class="text-[10px] text-zinc-500 font-mono">1-Click Swap</span>
                        </div>

                        <div class="space-y-1 py-1">
                            @foreach($this->favoriteModels as $fav)
                                <button
                                    type="button"
                                    wire:click="selectModel('{{ $fav['tuple'] }}')"
                                    class="w-full text-left px-2.5 py-2 rounded-lg text-xs transition-colors flex items-center justify-between {{ $fav['is_active'] ? 'bg-[#21232B] text-white border border-[#429A6A]/40' : 'text-zinc-300 hover:bg-[#21232B] hover:text-white border border-transparent' }}"
                                >
                                    <div class="flex items-center space-x-2 truncate">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $fav['is_active'] ? 'bg-[#429A6A]' : 'bg-zinc-600' }}"></span>
                                        <span class="truncate">{{ $fav['label'] }}</span>
                                    </div>
                                    @if($fav['is_active'])
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[#429A6A]/20 text-[#429A6A] shrink-0 font-medium">Active</span>
                                    @else
                                        <span class="text-[10px] text-zinc-500 hover:text-zinc-300 shrink-0 font-mono">Slot {{ $fav['slot'] }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>

                        <div class="pt-1.5 border-t border-[#2C2F38] px-2 py-1">
                            <a
                                href="/admin/executive-settings"
                                class="text-[11px] text-zinc-400 hover:text-[#C9A36D] transition-colors flex items-center justify-between group"
                            >
                                <span>Configure Favorites in Settings</span>
                                <svg style="width: 14px; height: 14px; min-width: 14px;" class="text-zinc-500 group-hover:text-[#C9A36D] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <button
                    wire:click="newSession"
                    type="button"
                    class="p-1.5 rounded-md hover:bg-[#21232B] text-[#9CA3AF] hover:text-white transition-colors"
                    title="New Session"
                >
                    <svg style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
                <button
                    wire:click="closeDrawer"
                    type="button"
                    class="p-1.5 rounded-md hover:bg-[#21232B] text-[#9CA3AF] hover:text-white transition-colors"
                    title="Close (Esc)"
                >
                    <svg style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Presets Ribbon -->
        <div class="px-5 py-2.5 bg-[#141519] border-b border-[#2C2F38] overflow-x-auto scrollbar-thin flex items-center space-x-2">
            <span class="text-[11px] font-medium text-[#9CA3AF] shrink-0 uppercase tracking-wider">Presets:</span>
            @forelse($this->presets as $preset)
                <button
                    wire:click="runPreset({{ $preset->id }})"
                    type="button"
                    class="shrink-0 px-2.5 py-1 text-xs rounded-full bg-[#18191E] hover:bg-[#21232B] text-zinc-300 hover:text-white border border-[#2C2F38] hover:border-[#C9A36D]/40 transition-colors flex items-center space-x-1.5"
                >
                    <span>{{ $preset->title }}</span>
                </button>
            @empty
                <span class="text-xs text-zinc-600">No active presets configured</span>
            @endforelse
        </div>

        <!-- Message History Stream -->
        <div
            id="copilot-message-stream"
            class="flex-1 overflow-y-auto p-5 space-y-4 scrollbar-thin"
        >
            @forelse($this->messages as $msg)
                @if($msg->role === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[85%] rounded-2xl rounded-tr-sm bg-[#C9A36D]/15 border border-[#C9A36D]/30 px-4 py-3 text-sm text-white shadow-sm">
                            {{ $msg->content }}
                        </div>
                    </div>
                @elseif($msg->role === 'assistant')
                    <div class="flex justify-start space-x-3">
                        <div class="w-7 h-7 rounded-lg bg-[#18191E] border border-[#2C2F38] flex items-center justify-center text-[#C9A36D] shrink-0 mt-0.5">
                            <svg style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div class="max-w-[85%] space-y-2">
                            <div class="rounded-2xl rounded-tl-sm bg-[#18191E] border border-[#2C2F38] px-4 py-3 text-sm text-zinc-200 shadow-sm leading-relaxed prose prose-invert prose-sm">
                                {!! nl2br(e($msg->content)) !!}
                            </div>
                        </div>
                    </div>
                @elseif($msg->role === 'tool')
                    <div class="flex justify-start">
                        <div class="max-w-[90%] rounded-lg bg-[#141519] border border-[#2C2F38]/60 px-3 py-2 text-xs font-mono text-zinc-400">
                            <span class="text-[#429A6A] font-semibold">✓ System Action Completed:</span>
                            <span class="truncate block mt-0.5">{{ $msg->content }}</span>
                        </div>
                    </div>
                @endif
            @empty
                <div class="h-full flex flex-col items-center justify-center text-center p-6 text-zinc-500">
                    <div class="w-12 h-12 rounded-xl bg-[#18191E] border border-[#2C2F38] flex items-center justify-center text-[#C9A36D] mb-3 shrink-0">
                        <svg style="width: 24px; height: 24px; min-width: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-zinc-300">Executive Workspace Ready</p>
                    <p class="text-xs text-zinc-500 mt-1 max-w-xs">
                        Ask about projects (<span class="font-mono">PC-2023-011</span>), scan Outlook correspondence, or execute quick action presets.
                    </p>
                </div>
            @endforelse

            <!-- Staged Action Dossier Card -->
            @if($suspendedToolCall !== null && $suspendedToolCall['name'] === 'propose_action_card')
                @php
                    $card = $suspendedToolCall['suspension_payload']['card'] ?? [];
                    $token = $suspendedToolCall['suspension_payload']['approval_token'] ?? '';
                    $payload = $card['payload'] ?? [];
                @endphp
                <div class="rounded-xl bg-[#21232B] border border-[#D4A373]/40 p-4 space-y-3 shadow-lg">
                    <div class="flex items-center justify-between border-b border-[#2C2F38] pb-2.5">
                        <div class="flex items-center space-x-2">
                            <span class="w-2 h-2 rounded-full bg-[#D4A373] animate-pulse"></span>
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#D4A373]">
                                {{ $card['title'] ?: 'Action Proposal Staged' }}
                            </h4>
                        </div>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-[#D4A373]/15 text-[#D4A373] border border-[#D4A373]/30">
                            {{ strtoupper(str_replace('_', ' ', (string)($card['action_type'] ?? 'DISPATCH'))) }}
                        </span>
                    </div>

                    <p class="text-xs text-zinc-300 leading-relaxed">{{ $card['summary'] }}</p>

                    @if(!empty($payload))
                        <div class="bg-[#141519] rounded-lg p-3 border border-[#2C2F38] text-xs font-mono text-zinc-300 space-y-1.5">
                            @if(isset($payload['to']))
                                <div><span class="text-zinc-500">To:</span> {{ is_array($payload['to']) ? implode(', ', $payload['to']) : $payload['to'] }}</div>
                            @endif
                            @if(isset($payload['subject']))
                                <div><span class="text-zinc-500">Subject:</span> {{ $payload['subject'] }}</div>
                            @endif
                            @if(isset($payload['body']))
                                <div class="mt-2 pt-2 border-t border-[#2C2F38] text-zinc-200 font-sans text-xs whitespace-pre-wrap leading-normal bg-[#18191E] p-2.5 rounded border border-[#2C2F38]/60">
                                    {{ $payload['body'] }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button
                            wire:click="discardActionCard('{{ $token }}')"
                            type="button"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium text-zinc-400 hover:text-white hover:bg-[#18191E] border border-transparent hover:border-[#2C2F38] transition-colors"
                        >
                            Discard
                        </button>
                        <button
                            wire:click="approveActionCard('{{ $token }}')"
                            type="button"
                            class="px-4 py-1.5 rounded-lg text-xs font-semibold bg-white text-zinc-950 hover:bg-zinc-100 shadow transition-all flex items-center space-x-1.5"
                        >
                            <svg style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Approve & Dispatch</span>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Interactive Choice Modal (ask_user_question) -->
            @if($suspendedToolCall !== null && $suspendedToolCall['name'] === 'ask_user_question')
                @php
                    $args = $suspendedToolCall['arguments'] ?? [];
                    $question = $args['question'] ?? 'Please select an option:';
                    $options = (array) ($args['options'] ?? []);
                    $isMulti = (bool) ($args['is_multi_select'] ?? false);
                    $allowCustom = (bool) ($args['allow_custom_input'] ?? true);
                @endphp
                <div class="rounded-xl bg-[#21232B] border border-[#C9A36D]/40 p-4 space-y-3 shadow-lg">
                    <div class="flex items-center justify-between border-b border-[#2C2F38] pb-2">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-[#C9A36D]">
                            Executive Clarification Needed
                        </h4>
                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-[#C9A36D]/15 text-[#C9A36D] border border-[#C9A36D]/30">
                            {{ $isMulti ? 'MULTI-SELECT' : 'SINGLE CHOICE' }}
                        </span>
                    </div>

                    <p class="text-sm font-medium text-white">{{ $question }}</p>

                    <div class="space-y-2 pt-1">
                        @foreach($options as $opt)
                            <label class="flex items-center space-x-2.5 p-2 rounded-lg bg-[#18191E] border border-[#2C2F38] hover:border-[#C9A36D]/40 cursor-pointer transition-colors text-xs text-zinc-200">
                                @if($isMulti)
                                    <input
                                        type="checkbox"
                                        wire:model="multiSelections"
                                        value="{{ $opt }}"
                                        class="rounded bg-[#111215] border-[#2C2F38] text-[#C9A36D] focus:ring-[#C9A36D]"
                                    />
                                @else
                                    <input
                                        type="radio"
                                        wire:model="choiceSelection"
                                        value="{{ $opt }}"
                                        name="choice_option"
                                        class="bg-[#111215] border-[#2C2F38] text-[#C9A36D] focus:ring-[#C9A36D]"
                                    />
                                @endif
                                <span>{{ $opt }}</span>
                            </label>
                        @endforeach
                    </div>

                    @if($allowCustom)
                        <div class="pt-1">
                            <input
                                type="text"
                                wire:model="choiceNotes"
                                placeholder="{{ $args['custom_input_placeholder'] ?? 'Additional instructions / notes (optional)...' }}"
                                class="w-full text-xs rounded-lg bg-[#141519] border border-[#2C2F38] text-white px-3 py-2 focus:ring-1 focus:ring-[#C9A36D] focus:border-[#C9A36D]"
                            />
                        </div>
                    @endif

                    <div class="pt-2 flex items-center justify-between">
                        <button
                            wire:click="skipChoice"
                            type="button"
                            class="text-xs text-zinc-400 hover:text-white"
                        >
                            Skip
                        </button>
                        <div class="flex items-center space-x-2">
                            <button
                                wire:click="cancelChoice"
                                type="button"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium text-zinc-400 hover:bg-[#18191E]"
                            >
                                Cancel
                            </button>
                            <button
                                wire:click="submitChoice"
                                type="button"
                                class="px-4 py-1.5 rounded-lg text-xs font-semibold bg-[#C9A36D] text-zinc-950 hover:bg-[#D4B896] shadow"
                            >
                                Submit Response
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Loading Spinner -->
            @if($isProcessing)
                <div class="flex items-center space-x-3 text-xs text-zinc-400 p-3 rounded-lg bg-[#18191E] border border-[#2C2F38]">
                    <svg class="animate-spin h-4 w-4 text-[#C9A36D]" style="width: 16px; height: 16px; min-width: 16px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ $statusMessage ?: 'Analyzing project memory and querying models...' }}</span>
                </div>
            @endif
        </div>

        <!-- Input Box -->
        <div class="p-4 bg-[#18191E] border-t border-[#2C2F38]">
            <form wire:submit.prevent="sendMessage">
                <div class="relative rounded-xl bg-[#141519] border border-[#2C2F38] focus-within:border-[#C9A36D]/60 focus-within:ring-1 focus-within:ring-[#C9A36D]/60 transition-all">
                    <textarea
                        wire:model="inputPrompt"
                        rows="2"
                        placeholder="Inquire project memory, draft correspondence, or scan delta... (Cmd+Enter to send)"
                        class="w-full bg-transparent border-0 text-sm text-white placeholder-zinc-500 focus:ring-0 resize-none px-3.5 py-2.5"
                        @keydown.cmd.enter.prevent="$wire.sendMessage()"
                        @keydown.ctrl.enter.prevent="$wire.sendMessage()"
                    ></textarea>

                    <div class="flex items-center justify-between px-3 py-2 border-t border-[#2C2F38]/40 bg-[#111215]/50">
                        <div class="text-[11px] text-zinc-500 flex items-center space-x-2">
                            <span><kbd class="px-1 py-0.5 rounded bg-[#18191E] border border-[#2C2F38] text-[10px] text-zinc-400">Cmd+J</kbd> toggle</span>
                            <span><kbd class="px-1 py-0.5 rounded bg-[#18191E] border border-[#2C2F38] text-[10px] text-zinc-400">Cmd+Enter</kbd> send</span>
                        </div>

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-[#C9A36D] hover:bg-[#D4B896] text-zinc-950 transition-colors flex items-center space-x-1.5 shadow"
                        >
                            <span>Send</span>
                            <svg style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
