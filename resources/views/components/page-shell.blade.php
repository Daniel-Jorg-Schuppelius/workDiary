@props([
    'gap'      => 4,
    'overflow' => 'auto',
    'height'   => 'standard',
])

{{--
    <x-page-shell> — kanonischer Außencontainer für Seiten im neuen Bereich.

    Vorbild: resources/views/week/index.blade.php (Wochensicht).
    Sorgt für einheitliche Höhe, Spacing und Scroll-Verhalten aller Seiten.

    Aufbau (App-Shell):
      - Der Toolbar-Header (Beschreibung + Aktionen der Seite) wird per
        @push('page-header') AUS dem <main> herausgehoben; das Layout rendert
        ihn per @stack('page-header') als eigenes, stehendes Panel ÜBER dem
        <main>. So bleibt der Seitenkopf stehen.
      - Dieser Container (= der Body) füllt die restliche Höhe des <main> und
        scrollt intern.

    Props:
      - gap      : Lücke zwischen Karten (Tailwind-Spacing, Default 4)
      - overflow : "auto" (Default, Body scrollt) | "clip" (Body regelt Scroll selbst)
      - height   : "standard" (Default, nutzt verfügbare Contenthöhe) | "content" (nur Inhaltshöhe)

    Slots:
      - toolbar (named) : optionale Toolbar-Karte (z. B. <x-page-toolbar>) — wird
                          oben über dem main als eigenes Panel gerendert
      - default         : Karten / Inhalt (scrollender Body)
--}}

@php
    $overflowClass = $overflow === 'clip' ? 'overflow-clip' : 'overflow-auto';
    $heightClass = $height === 'content' ? 'min-h-0' : 'h-full min-h-0 flex-1';
@endphp

@isset($toolbar)
    @push('page-header')
        <div class="shrink-0 mb-[var(--sidebar-gap)]">
            {{ $toolbar }}
        </div>
    @endpush
@endisset

<div {{ $attributes->class([
    'wd-page-shell flex w-full flex-col',
    $heightClass,
    "gap-{$gap}",
    $overflowClass,
]) }}>
    {{ $slot }}
</div>
