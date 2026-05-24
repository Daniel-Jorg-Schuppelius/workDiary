@props([
    'title' => null,
    'subtitle' => null,
    'badge' => null,
    'badgeTone' => 'primary',
])

{{--
    Einheitliche Toolbar für Content-Pages.
    Standard: Karte mit `bg-base-100`, Border + Schatten — wirkt in jedem DaisyUI-Theme korrekt.
    `title` ist optional, da der App-Header bereits den Seitentitel via `nav-title` zeigt;
    Untertitel/Sub-Kontext (z. B. Kundenname) kommen hier rein.
--}}
<div {{ $attributes->class([
    'flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs',
]) }}>
    <div class="min-w-0 flex flex-col gap-0.5">
        @if ($title || $badge)
            <div class="flex flex-wrap items-center gap-2 min-w-0">
                @if ($title)
                    <h2 class="font-['Space_Grotesk'] text-lg font-semibold text-base-content truncate">{{ $title }}</h2>
                @endif
                @if ($badge)
                    <span class="badge badge-sm badge-{{ $badgeTone }}">{{ $badge }}</span>
                @endif
            </div>
        @endif
        @if ($subtitle)
            <p class="text-xs text-base-content/60">{{ $subtitle }}</p>
        @endif
        @if (trim($slot) !== '')
            <div class="text-sm text-base-content/70">{{ $slot }}</div>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
