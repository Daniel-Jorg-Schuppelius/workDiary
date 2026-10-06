{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : frame.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Rahmen aller Diagramme (§Diagramm-UX an einer Stelle): Kopf mit Titel,
     Einheit, Zeitraum und Datenstand, Hinweiszeile, Leerzustand, Canvas für
     charts.js, SVG-Hülle und gleichwertige Datentabelle.

     Slots: Standard = Inhalt der Zeichnung, `legend` = Legendenzeilen,
     `head` und `rows` = Kopf und Zeilen der Datentabelle. Blade wertet die
     Slots auch im Leerzustand aus — sie müssen leere Daten vertragen. --}}

@props([
    'title',
    'unit',
    'range' => null,         // [erstes, letztes] Label der Reihe → „a – b“ hinter der Einheit
    'computedAt' => null,
    'note' => null,           // Datenbasis-Hinweis unter dem Titel (MVP-470)
    'empty' => false,
    'emptyIcon' => 'bar_chart',
    'spec' => null,          // Kontrakt für charts.js; ohne ihn kein Canvas
    'viewBox' => null,       // [Breite, Höhe]; ohne sie ist der Slot selbst die Darstellung (Heatmap)
])

<figure class="wd-chart rounded-box border border-base-300 bg-base-100 p-3">
    <figcaption>
        <span class="font-['Space_Grotesk'] text-sm font-semibold">{{ $title }}</span>
        <span class="ml-2 text-xs text-muted">
            {{ $unit }}
            @if ($range !== null) · {{ $range[0] }} – {{ $range[1] }} @endif
            @if ($computedAt) · {{ __('Stand:') }} {{ \Illuminate\Support\Carbon::parse($computedAt)->isoFormat('L LT') }} @endif
        </span>
    </figcaption>

    @if ($note)
        <p class="mt-1 text-xs text-muted">{{ $note }}</p>
    @endif

    @if ($empty)
        <div class="wd-chart-empty">
            <x-empty-state :icon="$emptyIcon" :title="__('Noch keine Daten für dieses Diagramm.')" compact />
        </div>
    @else
        @if ($spec !== null)
            @include('components.charts._canvas', ['spec' => $spec])
        @endif
        @if ($viewBox !== null)
            {{-- wd-chart-svg blendet charts.js aus, sobald das Canvas steht. --}}
            <svg viewBox="0 0 {{ $viewBox[0] }} {{ $viewBox[1] }}" role="img" aria-label="{{ $title }}" class="{{ $spec !== null ? 'wd-chart-svg ' : '' }}mt-2 w-full">
                {{ $slot }}
            </svg>
        @else
            {{ $slot }}
        @endif

        {{ $legend ?? '' }}

        @isset($head)
            <div class="wd-chart-table mt-2 max-h-48 overflow-y-auto">
                <x-table bare>
                    <x-slot:head>
                        {{ $head }}
                    </x-slot:head>
                    {{ $rows ?? '' }}
                </x-table>
            </div>
        @endisset
    @endif
</figure>
