<div
    x-data="{
        isOpen: false,
        activeTab: 'tasks'
    }"
    class="space-y-6"
>
    <!-- Top Action Bar & Status -->
    <div class="p-6 bg-[#18191E] rounded-xl shadow-sm border border-[#2C2F38] flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2.5">
                <span class="w-3 h-3 rounded-full bg-[#C9A36D]"></span>
                <h2 class="text-xl font-bold text-white tracking-wide">DPIK TADBIR — Executive Management</h2>
            </div>
            <p class="text-xs text-zinc-400 mt-1 max-w-2xl">
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

    <!-- Quick Start Preset Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div
            @click="$dispatch('ask-copilot-about', { subject: 'Today\'s Updates', context: 'Scan recent unread Outlook communications and summarize urgent deliverables for today' })"
            class="p-4 bg-[#18191E] hover:bg-[#21232B] border border-[#2C2F38] hover:border-[#C9A36D]/50 rounded-xl cursor-pointer transition-all flex items-start space-x-3.5 group"
        >
            <div class="w-9 h-9 rounded-lg bg-[#C9A36D]/15 text-[#C9A36D] flex items-center justify-center shrink-0">
                <svg style="width: 20px; height: 20px; min-width: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-white group-hover:text-[#C9A36D] transition-colors">Today's Updates</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Check unread emails and urgent messages for today.</p>
            </div>
        </div>

        <div
            @click="$dispatch('ask-copilot-about', { subject: 'Project Records & Tenders', context: 'Search active project notes, contracts, and engineering records' })"
            class="p-4 bg-[#18191E] hover:bg-[#21232B] border border-[#2C2F38] hover:border-[#429A6A]/50 rounded-xl cursor-pointer transition-all flex items-start space-x-3.5 group"
        >
            <div class="w-9 h-9 rounded-lg bg-[#429A6A]/15 text-[#429A6A] flex items-center justify-center shrink-0">
                <svg style="width: 20px; height: 20px; min-width: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-white group-hover:text-[#429A6A] transition-colors">Project Records</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Search project notes, tenders, and contracts.</p>
            </div>
        </div>

        <div
            @click="$dispatch('ask-copilot-about', { subject: 'Overdue Tasks & Pending Approvals', context: 'List all overdue tasks, pending submittals, and blocked milestones' })"
            class="p-4 bg-[#18191E] hover:bg-[#21232B] border border-[#2C2F38] hover:border-rose-500/50 rounded-xl cursor-pointer transition-all flex items-start space-x-3.5 group"
        >
            <div class="w-9 h-9 rounded-lg bg-rose-500/15 text-rose-400 flex items-center justify-center shrink-0">
                <svg style="width: 20px; height: 20px; min-width: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-white group-hover:text-rose-400 transition-colors">Overdue & Blockers</h3>
                <p class="text-xs text-zinc-400 mt-0.5">View pending approvals and delayed tasks.</p>
            </div>
        </div>
    </div>

    <!-- Live DPIK Tugas Action Register -->
    <div class="bg-[#18191E] rounded-xl border border-[#2C2F38] overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-[#2C2F38] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-500/15 text-indigo-400 flex items-center justify-center">
                    <svg style="width: 18px; height: 18px; min-width: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">DPIK Tugas — Task List</h3>
                    <p class="text-xs text-zinc-400">Live operational task tracking across active projects</p>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <a
                    href="/admin/personal-tasks/create"
                    class="px-3 py-1.5 rounded-lg bg-[#21232B] hover:bg-[#2C2F38] text-xs text-zinc-200 hover:text-white border border-[#2C2F38] transition-colors"
                >
                    + New Task
                </a>
                <button
                    wire:click="$refresh"
                    type="button"
                    class="px-3 py-1.5 rounded-lg bg-[#21232B] hover:bg-[#2C2F38] text-xs text-zinc-200 hover:text-white border border-[#2C2F38] transition-colors"
                >
                    Refresh
                </button>
            </div>
        </div>

        <div class="divide-y divide-[#2C2F38]">
            @php
                $tasks = \App\Models\PersonalTask::where('user_id', auth()->id())->latest()->take(6)->get();
            @endphp

            @forelse($tasks as $task)
                <div class="p-4 hover:bg-[#21232B]/50 transition-colors flex items-center justify-between gap-4">
                    <div class="flex items-center space-x-3 min-w-0">
                        <button
                            wire:click="toggleTaskStatus({{ $task->id }})"
                            type="button"
                            class="w-5 h-5 rounded-full border {{ $task->status === 'completed' ? 'bg-[#429A6A] border-[#429A6A] text-white' : 'border-zinc-600 hover:border-[#C9A36D]' }} flex items-center justify-center transition-colors shrink-0"
                            title="Toggle status"
                        >
                            @if($task->status === 'completed')
                                <svg style="width: 12px; height: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </button>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-white truncate {{ $task->status === 'completed' ? 'line-through text-zinc-500' : '' }}">
                                {{ $task->title }}
                            </p>
                            @if($task->description)
                                <p class="text-xs text-zinc-400 truncate">{{ $task->description }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center space-x-3 shrink-0">
                        @if($task->project_code)
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700">
                                {{ $task->project_code }}
                            </span>
                        @endif
                        <span class="text-[10px] px-2 py-0.5 rounded uppercase tracking-wider font-semibold {{ $task->status === 'completed' ? 'bg-[#429A6A]/20 text-[#429A6A]' : ($task->status === 'in_progress' ? 'bg-indigo-500/20 text-indigo-400' : 'bg-amber-500/20 text-amber-400') }}">
                            {{ str_replace('_', ' ', $task->status) }}
                        </span>
                        <button
                            @click="$dispatch('ask-copilot-about', { subject: 'Task: {{ addslashes($task->title) }}', context: 'Project: {{ $task->project_code ?? 'General' }}. Status: {{ $task->status }}' })"
                            type="button"
                            class="p-1.5 rounded-lg hover:bg-zinc-800 text-zinc-400 hover:text-[#C9A36D] transition-colors"
                            title="Analyze with AI"
                        >
                            <svg style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-zinc-500">
                    <p class="text-sm font-medium text-zinc-400">No active tasks in your DPIK Tugas workspace</p>
                    <p class="text-xs text-zinc-600 mt-1">Create a task or ask the AI Assistant to extract action items from emails.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
