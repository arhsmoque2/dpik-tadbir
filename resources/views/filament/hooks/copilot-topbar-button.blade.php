<div class="flex items-center space-x-2">
    <button
        x-data
        @click="$dispatch('toggle-copilot-drawer')"
        type="button"
        data-copilot-trigger
        class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-[#18191E] hover:bg-[#21232B] text-zinc-200 hover:text-white border border-[#2C2F38] hover:border-[#C9A36D]/50 text-xs font-medium transition-all shadow-sm group"
        title="Toggle AI Executive Copilot (Cmd+J)"
    >
        <span class="w-2 h-2 rounded-full bg-[#C9A36D] group-hover:scale-110 transition-transform"></span>
        <span class="tracking-wide">AI Copilot</span>
        <span class="hidden md:inline-block text-[10px] font-mono px-1.5 py-0.5 rounded bg-[#111215] text-zinc-400 border border-[#2C2F38]">
            ⌘J
        </span>
    </button>
</div>
