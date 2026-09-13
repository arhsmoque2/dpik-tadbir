<div
    x-data="{
        isOpen: @entangle('isOpen'),
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'o') {
                    e.preventDefault();
                    $wire.toggle();
                } else if (e.key === 'Escape' && this.isOpen) {
                    this.isOpen = false;
                    $wire.close();
                }
            });
        }
    }"
    @open-quick-switcher.window="isOpen = true; $wire.open()"
    x-cloak
>
    <!-- Modal Backdrop -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[70]"
        @click="$wire.close()"
        aria-hidden="true"
    ></div>

    <!-- Modal Panel -->
    <div
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        data-chat-quick-switcher
        class="fixed inset-x-4 top-[15%] md:inset-x-auto md:left-1/2 md:-translate-x-1/2 md:w-full md:max-w-xl z-[75] bg-[#18191E] border border-[#2C2F38] rounded-2xl shadow-2xl overflow-hidden flex flex-col text-[#F3F4F6]"
        role="dialog"
        aria-modal="true"
        aria-label="Conversation Quick Switcher"
    >
        <!-- Search Input Bar -->
        <div class="p-3 border-b border-[#2C2F38] flex items-center space-x-3 bg-[#141519]">
            <svg style="width: 18px; height: 18px; min-width: 18px;" class="text-[#C9A36D]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
                type="text"
                wire:model.live.debounce.150ms="search"
                placeholder="Search conversations by title... (Esc to close)"
                class="flex-1 bg-transparent border-none text-sm text-white placeholder-zinc-500 focus:outline-none focus:ring-0"
                x-ref="searchInput"
                x-init="$watch('isOpen', value => { if (value) $nextTick(() => $refs.searchInput.focus()) })"
            />
            <button
                type="button"
                wire:click="close"
                class="p-1 rounded-md text-zinc-400 hover:text-white hover:bg-[#21232B] transition-colors"
                title="Close (Esc)"
                aria-label="Close conversation quick switcher"
            >
                <svg style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Result List -->
        <div class="max-h-80 overflow-y-auto p-2 space-y-1 scrollbar-thin">
            <!-- New Conversation Action -->
            <button
                type="button"
                wire:click="createNewSession"
                class="w-full px-3 py-2.5 rounded-xl text-left text-xs text-[#C9A36D] hover:bg-[#21232B] border border-transparent hover:border-[#C9A36D]/30 transition-colors flex items-center space-x-2.5"
                title="Start a new conversation"
                aria-label="Start a new conversation"
            >
                <div class="w-6 h-6 rounded-lg bg-[#C9A36D]/15 border border-[#C9A36D]/30 flex items-center justify-center shrink-0">
                    <svg style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <span class="font-medium">+ New Conversation</span>
            </button>

            @forelse($this->results as $session)
                <button
                    type="button"
                    wire:click="selectSession({{ $session->id }})"
                    class="w-full px-3 py-2.5 rounded-xl text-left text-xs transition-colors flex items-center justify-between hover:bg-[#21232B] text-zinc-200 hover:text-white group border border-transparent hover:border-[#2C2F38]"
                    title="{{ $session->title }}"
                    aria-label="Select conversation {{ $session->title }}"
                >
                    <div class="flex items-center space-x-2.5 truncate">
                        <svg style="width: 16px; height: 16px; min-width: 16px;" class="text-zinc-500 group-hover:text-[#C9A36D] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        <div class="truncate">
                            <div class="font-medium truncate">{{ $session->title }}</div>
                            <div class="text-[10px] text-zinc-500 font-mono mt-0.5">
                                {{ $session->messages_count }} {{ \Illuminate\Support\Str::plural('message', $session->messages_count) }} · {{ $session->updated_at ? $session->updated_at->diffForHumans() : 'Recently' }}
                            </div>
                        </div>
                    </div>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                        Open
                    </span>
                </button>
            @empty
                <div class="py-8 text-center text-zinc-500 text-xs">
                    No conversations found matching "{{ $search }}"
                </div>
            @endforelse
        </div>

        <!-- Footer / Shortcut Hint -->
        <div class="px-4 py-2 border-t border-[#2C2F38] bg-[#141519] flex items-center justify-between text-[11px] text-zinc-500 font-mono">
            <span>Navigation: <kbd class="px-1 py-0.5 rounded bg-zinc-800 text-zinc-300">Cmd+O</kbd></span>
            <span>Close: <kbd class="px-1 py-0.5 rounded bg-zinc-800 text-zinc-300">Esc</kbd></span>
        </div>
    </div>
</div>
