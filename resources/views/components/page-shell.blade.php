@props([
    'gap'      => 4,
    'overflow' => 'auto',
])

{{--
    <x-page-shell> — kanonischer Außencontainer für Seiten im neuen Bereich.

    Vorbild: resources/views/week/index.blade.php (Wochensicht).
    Sorgt für einheitliche Höhe, Spacing und Scroll-Verhalten aller Seiten.

    Props:
      - gap      : Lücke zwischen Karten (Tailwind-Spacing, Default 4)
      - overflow : "auto" (Default, ganze Seite scrollt) | "clip" (Inhalte regeln Scroll selbst)

    Slots:
      - toolbar (named) : optionale Toolbar-Karte (z. B. <x-page-toolbar>) oben
      - default         : Karten / Inhalt
--}}

@php
    $overflowClass = $overflow === 'clip' ? 'overflow-clip' : 'overflow-auto';
@endphp

<div {{ $attributes->class([
    'wd-page-shell flex h-full min-h-0 w-full flex-col',
    "gap-{$gap}",
    $overflowClass,
]) }}>
    @isset($toolbar)
        {{ $toolbar }}
    @endisset

    {{ $slot }}
</div>
