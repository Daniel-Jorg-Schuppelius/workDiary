@props([
    'title'    => null,
    'subtitle' => null,
    'padding'  => 'p-4',
    'as'       => 'div',
    'icon'     => null,
    'count'    => null,
])

{{--
    <x-card> — kanonische weiße Karte für den neuen Bereich.

    Markup-Standard: rounded-box + border-base-300 + bg-base-100 + shadow-xs.
    Wird einheitlich auf allen Seiten als Inhalts-Container verwendet.

    Props:
      - title    : optionale Überschrift (h2)
      - subtitle : optionaler Untertitel
      - padding  : Tailwind-Padding-Klasse(n), Default "p-4". "p-0" für Tabellen-Karten.
      - as       : HTML-Tag (Default "div", z. B. "section")

    Slots:
      - default        : Karteninhalt
      - actions (named): Rechts-Buttons im Header
--}}

@php
    $hasHeader = $title || $subtitle || isset($actions);
    $hasPadding = $padding !== 'p-0';
@endphp

<{{ $as }} {{ $attributes->class([
    'wd-card rounded-box border border-base-300 bg-base-100 shadow-xs',
    $padding,
]) }}>
    @if ($hasHeader)
        <div @class([
            'flex flex-wrap items-start justify-between gap-3',
            'mb-3' => $hasPadding,
            'border-b border-base-300 px-4 py-3' => ! $hasPadding,
        ])>
            <div class="min-w-0">
                @if ($title)
                    <h2 class="flex items-center gap-2 font-['Space_Grotesk'] text-base font-semibold text-base-content">
                        @if ($icon)
                            <x-icon :name="$icon" class="text-base-content/60" />
                        @endif
                        <span class="truncate">{{ $title }}</span>
                        @if ($count !== null)
                            <span class="font-normal text-base-content/50">({{ $count }})</span>
                        @endif
                    </h2>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-base-content/60">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</{{ $as }}>
