@auth
@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $rawSlots = $user ? $user->getBottomNavSlots() : [];
    $slots = array_values($rawSlots);
    $leftSlots = array_slice($slots, 0, 2);
    $rightSlots = array_slice($slots, 2, 2);

    $renderIcon = function(string $key) {
        return match($key) {
            'bundles', 'mail' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />',
            'notes' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />',
            'tasks' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
            'projects', 'register' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />',
            'settings' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
            'copilot' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />',
            default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />',
        };
    };
@endphp
<div
    x-data="{
        activeTab: '{{ request()->path() }}'
    }"
    class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 rounded-full"
    style="background: #1e222b;"
    role="navigation"
    aria-label="Floating Primary Navigation"
>
    <div
        class="relative flex items-center justify-between gap-2 px-4 py-2 rounded-full border shadow-2xl backdrop-blur-xl"
        style="height: 62px; min-width: 360px; max-width: 480px; background: #1e222b; border-color: rgba(255, 255, 255, 0.18); box-shadow: 0 16px 36px rgba(0, 0, 0, 0.55); color: #FFFFFF;"
    >
        <!-- Left Slots -->
        @foreach($leftSlots as $slot)
            <a
                href="{{ $slot['url'] }}"
                class="flex flex-col items-center justify-center flex-1 transition-all hover:opacity-80"
                style="color: #FFFFFF; text-decoration: none;"
                title="{{ $slot['label'] }}"
                aria-label="{{ $slot['label'] }}"
            >
                <svg style="width: 18px; height: 18px; min-width: 18px; color: #FFFFFF;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    {!! $renderIcon($slot['key'] ?? '') !!}
                </svg>
                <span style="color: #FFFFFF; font-size: 10px; line-height: 12px; margin-top: 3px; font-weight: 600;">{{ $slot['label'] }}</span>
            </a>
        @endforeach

        <!-- Center Elevated Capsule Button with Explicit Text -->
        <div class="relative flex items-center justify-center px-1">
            <button
                x-data
                @click="$dispatch('toggle-copilot-drawer')"
                type="button"
                data-copilot-center-fab
                class="absolute -top-5 flex items-center justify-center shadow-2xl transition-transform hover:scale-105 active:scale-95 group"
                style="padding: 8px 16px; border-radius: 9999px; background: linear-gradient(135deg, #D4AF37 0%, #B88E52 100%); border: 2.5px solid #212631; box-shadow: 0 6px 20px rgba(212, 175, 55, 0.45); gap: 6px; cursor: pointer;"
                title="Launch AI Copilot (⌘J)"
                aria-label="Launch AI Copilot"
            >
                <svg style="width: 16px; height: 16px; min-width: 16px; color: #111215;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span style="color: #111215; font-size: 11px; line-height: 12px; font-weight: 800; letter-spacing: 0.02em; text-transform: uppercase;">AI Copilot</span>
            </button>
        </div>

        <!-- Right Slots -->
        @foreach($rightSlots as $slot)
            <a
                href="{{ $slot['url'] }}"
                class="flex flex-col items-center justify-center flex-1 transition-all hover:opacity-80"
                style="color: #FFFFFF; text-decoration: none;"
                title="{{ $slot['label'] }}"
                aria-label="{{ $slot['label'] }}"
            >
                <svg style="width: 18px; height: 18px; min-width: 18px; color: #FFFFFF;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    {!! $renderIcon($slot['key'] ?? '') !!}
                </svg>
                <span style="color: #FFFFFF; font-size: 10px; line-height: 12px; margin-top: 3px; font-weight: 600;">{{ $slot['label'] }}</span>
            </a>
        @endforeach
    </div>
</div>
@endauth
