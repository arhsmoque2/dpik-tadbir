<div
    x-data="{
        activeTab: '{{ request()->path() }}'
    }"
    class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40"
    role="navigation"
    aria-label="Floating Primary Navigation"
>
    <div
        class="relative flex items-center justify-between gap-1 px-3 py-2 rounded-full border shadow-2xl backdrop-blur-xl"
        style="height: 60px; min-width: 320px; max-width: 420px; background: rgba(24, 25, 30, 0.85); border-color: rgba(255, 255, 255, 0.12); box-shadow: 0 16px 32px rgba(0, 0, 0, 0.5);"
    >
        <!-- Slot 1: Dashboard -->
        <a
            href="/admin"
            class="flex flex-col items-center justify-center flex-1 text-xs text-zinc-400 hover:text-white transition-colors"
            title="Dashboard"
        >
            <svg style="width: 20px; height: 20px; min-width: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="text-[10px] mt-0.5 tracking-tight">Home</span>
        </a>

        <!-- Slot 2: DPIK Tugas -->
        <a
            href="/admin/personal-tasks"
            class="flex flex-col items-center justify-center flex-1 text-xs text-zinc-400 hover:text-white transition-colors"
            title="DPIK Tugas Task Register"
        >
            <svg style="width: 20px; height: 20px; min-width: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span class="text-[10px] mt-0.5 tracking-tight">Tugas</span>
        </a>

        <!-- Center FAB Gap & Elevated AI Button -->
        <div class="relative flex items-center justify-center px-2">
            <button
                x-data
                @click="$dispatch('toggle-copilot-drawer')"
                type="button"
                data-copilot-center-fab
                class="absolute -top-7 flex items-center justify-center rounded-full shadow-2xl transition-transform hover:scale-105 active:scale-95 group"
                style="width: 52px; height: 52px; background: linear-gradient(135deg, #C9A36D 0%, #B88E52 100%); border: 3px solid #18191E; box-shadow: 0 4px 16px rgba(201, 163, 109, 0.4);"
                title="Launch DPIK AI Copilot (⌘J)"
            >
                <svg style="width: 24px; height: 24px; min-width: 24px; color: #111215;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </button>
        </div>

        <!-- Slot 3: Project Memory Register -->
        <a
            href="/admin/project-registers"
            class="flex flex-col items-center justify-center flex-1 text-xs text-zinc-400 hover:text-white transition-colors"
            title="Project Register"
        >
            <svg style="width: 20px; height: 20px; min-width: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <span class="text-[10px] mt-0.5 tracking-tight">Memory</span>
        </a>

        <!-- Slot 4: Settings & Integrations -->
        <a
            href="/admin/executive-settings"
            class="flex flex-col items-center justify-center flex-1 text-xs text-zinc-400 hover:text-white transition-colors"
            title="Executive Settings"
        >
            <svg style="width: 20px; height: 20px; min-width: 20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="text-[10px] mt-0.5 tracking-tight">Settings</span>
        </a>
    </div>
</div>
