<div
    x-data="{
        isOpen: false,
        activeTab: 'tasks'
    }"
    class="space-y-6"
>
    <!-- Top Action Bar & Status -->
    <div class="p-6 bg-[#212631] rounded-xl shadow-sm border border-[#323946] flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2.5">
                <span class="w-3 h-3 rounded-full bg-[#C9A36D]"></span>
                <h2 class="text-xl font-bold text-white tracking-wide">DPIK TADBIR — Executive Management</h2>
            </div>
            <p class="text-xs text-zinc-300 mt-1 max-w-2xl">
                Daily management console for DPIK project registers, task tracking, and Microsoft Outlook correspondence.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <button
                @click="$dispatch('toggle-copilot-drawer')"
                type="button"
                class="px-4 py-2.5 rounded-lg bg-[#C9A36D] hover:bg-[#D4B896] text-zinc-950 font-semibold text-xs tracking-wide shadow-md transition-all flex items-center space-x-2"
            >
                <svg style="width: 16px; height: 16px; min-width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>AI Assistant (⌘J)</span>
            </button>
        </div>
    </div>

    <!-- Tab Switcher Navigation -->
    <div class="flex items-center space-x-2 border-b border-[#323946] pb-2">
        <button
            @click="activeTab = 'sessions'"
            type="button"
            :class="activeTab === 'sessions' ? 'bg-[#C9A36D] text-zinc-950 font-bold' : 'bg-[#212631] text-zinc-300 hover:text-white border border-[#323946]'"
            class="px-4 py-2 rounded-lg text-xs tracking-wide transition-all flex items-center space-x-2"
        >
            <svg style="width: 15px; height: 15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <span>Executive AI Sessions</span>
        </button>

        <button
            @click="activeTab = 'tasks'"
            type="button"
            :class="activeTab === 'tasks' ? 'bg-[#C9A36D] text-zinc-950 font-bold' : 'bg-[#212631] text-zinc-300 hover:text-white border border-[#323946]'"
            class="px-4 py-2 rounded-lg text-xs tracking-wide transition-all flex items-center space-x-2"
        >
            <svg style="width: 15px; height: 15px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Personal Action Tasks</span>
        </button>

        <div class="flex-1"></div>

        <div class="flex items-center space-x-2">
            <a
                href="/admin/sessions/export/json"
                target="_blank"
                class="px-3 py-1.5 rounded-lg bg-[#282E3C] hover:bg-[#323946] text-xs text-zinc-200 hover:text-white border border-[#323946] transition-colors flex items-center space-x-1.5"
                title="Export Sessions as JSON"
            >
                <svg style="width: 13px; height: 13px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Export JSON</span>
            </a>
            <a
                href="/admin/sessions/export/markdown"
                target="_blank"
                class="px-3 py-1.5 rounded-lg bg-[#282E3C] hover:bg-[#323946] text-xs text-zinc-200 hover:text-white border border-[#323946] transition-colors flex items-center space-x-1.5"
                title="Export Sessions as Markdown"
            >
                <svg style="width: 13px; height: 13px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Export Markdown</span>
            </a>
        </div>
    </div>

    <!-- Live Executive AI Sessions Register -->
    <div x-show="activeTab === 'sessions'" class="bg-[#212631] rounded-xl border border-[#323946] overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-[#323946] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-[#C9A36D]/15 text-[#C9A36D] flex items-center justify-center">
                    <svg style="width: 18px; height: 18px; min-width: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">Executive AI Sessions</h3>
                    <p class="text-xs text-zinc-300">Active conversation threads, contextual memory, and session history</p>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <button
                    wire:click="startNewSession"
                    type="button"
                    class="px-3.5 py-1.5 rounded-lg bg-[#C9A36D] hover:bg-[#D4B896] text-zinc-950 font-semibold text-xs transition-colors flex items-center space-x-1.5 shadow-sm"
                >
                    <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>New Session</span>
                </button>
                <button
                    wire:click="$refresh"
                    type="button"
                    class="px-3 py-1.5 rounded-lg bg-[#282E3C] hover:bg-[#323946] text-xs text-zinc-200 hover:text-white border border-[#323946] transition-colors"
                >
                    Refresh
                </button>
            </div>
        </div>

        <div class="divide-y divide-[#323946]">
            @php
                $sessions = \App\Models\ChatSession::where('user_id', auth()->id())->withCount('messages')->latest('updated_at')->take(10)->get();
            @endphp

            @forelse($sessions as $session)
                <div class="p-4 hover:bg-[#282E3C]/60 transition-colors flex items-center justify-between gap-4">
                    <div 
                        @click="$dispatch('open-copilot-drawer', { sessionId: {{ $session->id }} })"
                        class="flex items-center space-x-3.5 min-w-0 cursor-pointer flex-1 group"
                    >
                        <div class="w-8 h-8 rounded-lg bg-[#282E3C] group-hover:bg-[#C9A36D]/15 text-zinc-400 group-hover:text-[#C9A36D] flex items-center justify-center transition-colors shrink-0 border border-[#323946]">
                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-white group-hover:text-[#C9A36D] transition-colors truncate">
                                {{ $session->title }}
                            </p>
                            <p class="text-xs text-zinc-400 mt-0.5 flex items-center space-x-2">
                                <span>{{ $session->messages_count }} {{ \Illuminate\Support\Str::plural('message', $session->messages_count) }}</span>
                                <span>·</span>
                                <span>{{ $session->updated_at->diffForHumans() }}</span>
                                <span>·</span>
                                <span class="uppercase text-[10px] px-1.5 py-0.2 rounded bg-zinc-800 text-zinc-300 border border-zinc-700">
                                    {{ $session->context_mode ?? 'executive' }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 shrink-0">
                        <button
                            @click="$dispatch('open-copilot-drawer', { sessionId: {{ $session->id }} })"
                            type="button"
                            class="px-3 py-1.5 rounded-lg bg-[#282E3C] hover:bg-[#C9A36D]/20 text-xs font-medium text-zinc-200 hover:text-[#C9A36D] border border-[#323946] hover:border-[#C9A36D]/40 transition-all flex items-center space-x-1.5"
                            title="Resume Conversation"
                        >
                            <span>Resume</span>
                            <svg style="width: 12px; height: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <button
                            wire:click="deleteSession({{ $session->id }})"
                            wire:confirm="Delete this chat session history?"
                            type="button"
                            class="p-1.5 rounded-lg hover:bg-red-500/15 text-zinc-400 hover:text-red-400 transition-colors"
                            title="Delete Session"
                        >
                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-zinc-400">
                    <p class="text-sm font-medium text-zinc-300">No active AI sessions found</p>
                    <p class="text-xs text-zinc-400 mt-1">Start a new session to begin an executive briefing or contextual analysis.</p>
                    <button
                        wire:click="startNewSession"
                        type="button"
                        class="mt-4 px-4 py-2 rounded-lg bg-[#C9A36D] hover:bg-[#D4B896] text-zinc-950 font-semibold text-xs tracking-wide shadow-md transition-all inline-flex items-center space-x-1.5"
                    >
                        <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Start New Session</span>
                    </button>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Personal Action Tasks View -->
    <div x-show="activeTab === 'tasks'" class="bg-[#212631] rounded-xl border border-[#323946] overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-[#323946] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-[#C9A36D]/15 text-[#C9A36D] flex items-center justify-center">
                    <svg style="width: 18px; height: 18px; min-width: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">Personal Action Tasks & Reminders</h3>
                    <p class="text-xs text-zinc-300">Private tasks captured from Copilot actions or direct entry</p>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <a
                    href="/admin/personal-tasks"
                    class="px-3.5 py-1.5 rounded-lg bg-[#282E3C] hover:bg-[#323946] text-xs text-zinc-200 hover:text-white border border-[#323946] transition-colors flex items-center space-x-1.5"
                >
                    <span>Manage All Tasks</span>
                </a>
            </div>
        </div>

        <div class="divide-y divide-[#323946]">
            @php
                $tasks = \App\Models\PersonalTask::where('user_id', auth()->id())->latest('updated_at')->take(15)->get();
            @endphp

            @forelse($tasks as $task)
                <div class="p-4 hover:bg-[#282E3C]/60 transition-colors flex items-center justify-between gap-4">
                    <div class="flex items-center space-x-3 min-w-0 flex-1">
                        <button
                            wire:click="toggleTaskStatus({{ $task->id }})"
                            type="button"
                            class="w-6 h-6 rounded-full border flex items-center justify-center transition-colors {{ $task->status === 'completed' ? 'bg-emerald-500/20 border-emerald-500 text-emerald-400' : 'border-zinc-500 hover:border-[#C9A36D]' }}"
                            title="Toggle Task Status"
                        >
                            @if($task->status === 'completed')
                                <svg style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </button>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium {{ $task->status === 'completed' ? 'line-through text-zinc-500' : 'text-white' }} truncate">
                                {{ $task->title }}
                            </p>
                            <p class="text-xs text-zinc-400 mt-0.5 flex items-center space-x-2">
                                @if($task->project_code)
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                        {{ $task->project_code }}
                                    </span>
                                @endif
                                @if($task->due_date)
                                    <span>Due {{ $task->due_date->format('d M Y') }}</span>
                                    <span>·</span>
                                @endif
                                <span>{{ $task->created_at->diffForHumans() }}</span>
                            </p>
                        </div>
                    </div>

                    <div>
                        <span class="px-2 py-0.5 text-[11px] rounded-full {{ $task->status === 'completed' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-zinc-700 text-zinc-300' }}">
                            {{ ucfirst($task->status) }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-zinc-400">
                    <p class="text-sm font-medium text-zinc-300">No personal tasks recorded</p>
                    <p class="text-xs text-zinc-400 mt-1">Ask Copilot to capture action items or create tasks in the Personal Tasks resource.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
