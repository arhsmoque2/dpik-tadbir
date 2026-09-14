<div
    data-chat-sidebar-nav
    class="px-3 py-3 mt-2 border-t border-gray-200 dark:border-white/10"
>
    <!-- Section Header with Quick Actions -->
    <div class="flex items-center justify-between mb-2 px-1">
        <span class="text-[11px] font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">
            Recent Chats
        </span>
        <div class="flex items-center space-x-1">
            <button
                type="button"
                wire:click="$dispatch('open-quick-switcher')"
                class="p-1 rounded hover:bg-gray-100 dark:hover:bg-white/5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                title="Search conversations (Cmd+O)"
                aria-label="Search conversations"
            >
                <svg style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
            <button
                type="button"
                wire:click="createNewSession"
                class="p-1 rounded hover:bg-gray-100 dark:hover:bg-white/5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                title="New conversation"
                aria-label="New conversation"
            >
                <svg style="width: 14px; height: 14px; min-width: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Conversation List -->
    <ul class="space-y-1">
        @forelse($this->sessions as $session)
            <li class="group relative flex items-center justify-between rounded-lg px-2 py-1.5 text-xs transition-colors {{ $activeSessionId === $session->id ? 'bg-[#C9A36D]/15 text-[#C9A36D] font-medium' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/5' }}">
                @if($renamingSessionId === $session->id)
                    <form wire:submit.prevent="saveRename" class="flex-1 flex items-center space-x-1">
                        <input
                            type="text"
                            wire:model="renameTitle"
                            class="w-full bg-white dark:bg-zinc-800 border border-gray-300 dark:border-zinc-700 rounded px-1.5 py-0.5 text-xs text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-[#C9A36D]"
                            autofocus
                        />
                        <button
                            type="submit"
                            class="p-0.5 text-green-500 hover:text-green-400"
                            title="Save title"
                            aria-label="Save title"
                        >
                            <svg style="width: 12px; height: 12px; min-width: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            wire:click="cancelRename"
                            class="p-0.5 text-gray-400 hover:text-gray-300"
                            title="Cancel rename"
                            aria-label="Cancel rename"
                        >
                            <svg style="width: 12px; height: 12px; min-width: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </form>
                @else
                    <button
                        type="button"
                        wire:click="selectSession({{ $session->id }})"
                        class="flex-1 text-left truncate flex items-center space-x-2"
                        title="{{ $session->title }}"
                        aria-label="Open conversation {{ $session->title }}"
                    >
                        <svg style="width: 14px; height: 14px; min-width: 14px;" class="shrink-0 text-gray-400 group-hover:text-[#C9A36D]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        <span class="truncate">{{ $session->title }}</span>
                    </button>

                    <!-- Hover Actions (Rename, Delete) -->
                    <div class="opacity-0 group-hover:opacity-100 flex items-center space-x-1 shrink-0 ml-1 transition-opacity">
                        <button
                            type="button"
                            wire:click="startRenaming({{ $session->id }})"
                            class="p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            title="Rename conversation"
                            aria-label="Rename conversation"
                        >
                            <svg style="width: 12px; height: 12px; min-width: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            wire:click="deleteSession({{ $session->id }})"
                            class="p-0.5 text-gray-400 hover:text-red-500"
                            title="Delete conversation"
                            aria-label="Delete conversation"
                        >
                            <svg style="width: 12px; height: 12px; min-width: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                @endif
            </li>
        @empty
            <li class="px-2 py-2 text-xs text-gray-400 dark:text-gray-500 italic">
                No recent conversations
            </li>
        @endforelse
    </ul>
</div>
