<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Header -->
        <div class="p-6 bg-[#18191E] rounded-xl shadow-sm border border-[#2C2F38] flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2.5">
                    <span class="w-3 h-3 rounded-full bg-[#C9A36D]"></span>
                    <h2 class="text-xl font-bold text-white tracking-wide">DPIK Executive Copilot Command Center</h2>
                </div>
                <p class="text-xs text-zinc-400 mt-1 max-w-xl">
                    Sovereign executive workspace with zero-raw-storage Microsoft Outlook Graph processing, SQLite FTS5 RRF enterprise memory, and fail-closed human-in-the-loop action approval.
                </p>
            </div>

            <div class="flex items-center space-x-3">
                <button
                    x-data
                    @click="$dispatch('toggle-copilot-drawer')"
                    type="button"
                    class="px-4 py-2 rounded-lg bg-[#C9A36D] hover:bg-[#D4B896] text-zinc-950 font-semibold text-xs tracking-wide shadow-md transition-all flex items-center space-x-2"
                >
                    <svg class="w-4 h-4 text-zinc-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Launch AI Copilot Drawer (⌘J)</span>
                </button>
            </div>
        </div>

        <!-- Quick Start Action Presets Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
                x-data
                @click="$dispatch('ask-copilot-about', { subject: 'Daily Morning Delta Briefing', context: 'Scan recent unread Outlook communications and surface high-priority blockers' })"
                class="p-5 bg-[#18191E] hover:bg-[#21232B] border border-[#2C2F38] hover:border-[#C9A36D]/50 rounded-xl cursor-pointer transition-all group"
            >
                <div class="w-10 h-10 rounded-lg bg-[#C9A36D]/15 text-[#C9A36D] flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-white group-hover:text-[#C9A36D] transition-colors">Morning Delta Briefing</h3>
                <p class="text-xs text-zinc-400 mt-1">Scan unread Outlook messages in the past 24h for urgent client inquiries and action items.</p>
            </div>

            <div
                x-data
                @click="$dispatch('ask-copilot-about', { subject: 'Project Register Commitments', context: 'Extract recent project decisions and update shared domain registers' })"
                class="p-5 bg-[#18191E] hover:bg-[#21232B] border border-[#2C2F38] hover:border-[#C9A36D]/50 rounded-xl cursor-pointer transition-all group"
            >
                <div class="w-10 h-10 rounded-lg bg-[#429A6A]/15 text-[#429A6A] flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-white group-hover:text-[#429A6A] transition-colors">Project Memory Query</h3>
                <p class="text-xs text-zinc-400 mt-1">Query FTS5 BM25 knowledge index across engineering contracts, claim numbers, and meeting records.</p>
            </div>

            <div
                x-data
                @click="$dispatch('ask-copilot-about', { subject: 'Delivery Blockers & Pending Inquiries', context: 'Analyze overdue tasks and stalled project milestones' })"
                class="p-5 bg-[#18191E] hover:bg-[#21232B] border border-[#2C2F38] hover:border-[#C9A36D]/50 rounded-xl cursor-pointer transition-all group"
            >
                <div class="w-10 h-10 rounded-lg bg-rose-500/15 text-rose-400 flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-white group-hover:text-rose-400 transition-colors">Delivery Bottlenecks</h3>
                <p class="text-xs text-zinc-400 mt-1">Isolate overdue action items, stalled submittals, and pending variation order approvals.</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
