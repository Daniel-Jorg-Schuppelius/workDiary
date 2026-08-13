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
      - overflow : "auto" (Default, Body scrollt) | "clip" (Body regelt Scroll selbst;
                   vertikal bleibt ein Auto-Fallback für Mindesthöhen, s. u.)
      - height   : "standard" (Default, nutzt verfügbare Contenthöhe) | "content" (nur Inhaltshöhe)

    Slots:
      - toolbar (named) : optionale Toolbar-Karte (z. B. <x-page-toolbar>) — wird
                          oben über dem main als eigenes Panel gerendert
      - default         : Karten / Inhalt (scrollender Body)
--}}

@php
    // "clip" clippt nur horizontal; vertikal bleibt ein Auto-Scroll-Fallback:
    // Kinder mit Mindesthöhe (z. B. <x-table scroll="flex">, --wd-table-min-h)
    // dürfen bei zu niedrigem Viewport nicht unerreichbar abgeschnitten werden —
    // dann scrollt die Shell. Solange der Inhalt passt (Normalfall: flex-1-Kinder
    // schrumpfen exakt auf die verfügbare Höhe), erscheint kein Scrollbalken,
    // das Verhalten ist identisch zum früheren overflow-clip.
    $overflowClass = $overflow === 'clip' ? 'overflow-x-clip overflow-y-auto' : 'overflow-auto';
    $heightClass = $height === 'content' ? 'min-h-0' : 'h-full min-h-0 flex-1';
@endphp

@isset($toolbar)
    @push('page-header')
        {{-- max-md:px-3 gleicht den Seitenrand der Toolbar auf Handys an das
             Main-Padding (--wd-main-pad = 0.75rem) an. Sonst sitzt die Toolbar-
             Karte breiter als die Inhaltskarten (sichtbar, weil das Main-Panel
             mobil transparent ist → zwei verschiedene Kartenränder). Ab md ist
             das Main wieder ein Panel → kein Extra-Padding nötig. --}}
        <div class="shrink-0 mb-(--sidebar-gap) max-md:px-1">
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
