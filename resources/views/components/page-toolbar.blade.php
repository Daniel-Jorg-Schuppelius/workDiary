@props([
    'title' => null,
    'subtitle' => null,
    'badge' => null,
    'badgeTone' => 'primary',
])

{{--
    <x-page-toolbar> — Einheitliche Toolbar-Karte oben auf Content-Pages.

    Corporate-Design-Standard (Index-Seiten):
      - KEIN `title` (der Seitentitel kommt aus @section('nav-title') im Layout).
      - `:subtitle` als kurze Beschreibung ist Pflicht.
      - Rechte Aktionen via Slot `actions` (z. B. <x-icon-btn icon="add">).

    Für Index-Seiten bevorzugt <x-index-page> verwenden, das diese Toolbar
    bereits in <x-page-shell> einbettet.

    Standard-Optik: `bg-base-100`, Border + Schatten — wirkt in jedem
    DaisyUI-Theme korrekt.
--}}
<div {{ $attributes->class([
    // shrink-0: in Voll-Höhe-Flex-Seiten darf der Kopf nicht von hohem
    // Inhalt (z. B. großen Tabellen) zusammengestaucht werden.
    'flex min-h-16 shrink-0 flex-wrap items-center justify-between gap-3 rounded-[var(--panel-radius)] border border-base-300 bg-base-100 p-4 shadow-xs',
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
