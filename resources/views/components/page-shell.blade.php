@props([
    'gap'      => 4,
    'overflow' => 'auto',
    'height'   => 'standard',
])

{{--
    <x-page-shell> — kanonischer Außencontainer für Seiten im neuen Bereich.

    Vorbild: resources/views/week/index.blade.php (Wochensicht).
    Sorgt für einheitliche Höhe, Spacing und Scroll-Verhalten aller Seiten.

    Props:
      - gap      : Lücke zwischen Karten (Tailwind-Spacing, Default 4)
      - overflow : "auto" (Default, ganze Seite scrollt) | "clip" (Inhalte regeln Scroll selbst)
      - height   : "standard" (Default, nutzt verfügbare Contenthöhe) | "content" (nur Inhaltshöhe)

    Slots:
      - toolbar (named) : optionale Toolbar-Karte (z. B. <x-page-toolbar>) oben
      - default         : Karten / Inhalt
--}}

@php
    $overflowClass = $overflow === 'clip' ? 'overflow-clip' : 'overflow-auto';
    $heightClass = $height === 'content' ? 'min-h-0' : 'h-full min-h-0 flex-1';
@endphp

<div {{ $attributes->class([
    'wd-page-shell flex w-full flex-col',
    $heightClass,
    "gap-{$gap}",
    $overflowClass,
]) }}>
    @isset($toolbar)
        {{ $toolbar }}
    @endisset

    {{ $slot }}
</div>
