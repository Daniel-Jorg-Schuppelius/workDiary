{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : table.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'size'         => 'sm',
    'zebra'        => true,
    'pinRows'      => true,
    'scroll'       => 'x',
    'tableSort'    => 'none',  // none | client | server
    'route'        => null,    // nur sort=server
    'currentSort'  => null,    // nur sort=server (aktueller Sort-Schlüssel)
    'currentDir'   => 'desc',  // nur sort=server
    'sortParams'   => [],      // nur sort=server (zusätzliche Query-Parameter)
    'bare'         => false,   // wenn true: kein border/rounded-box/shadow am Wrapper
                               // (z.B. wenn die Tabelle bereits in einer Card mit Header sitzt)
    'empty'        => false,   // Leerzustand erzwingen (auch bei nicht-leerem Slot)
    'emptyTitle'   => null,
    'emptyMessage' => null,
    'emptyIcon'    => null,
    'autoEmpty'    => true,     // Standard: Leerzustand automatisch, wenn keine Zeile gerendert wurde
    'caption'      => null,    // Barrierefreiheit: sr-only <caption> als Tabellenname für Screenreader
])

{{--
    <x-table> — kanonische Tabellen-Karte.

    Props:
      - size, zebra, pinRows : DaisyUI-Tabellenoptionen
      - scroll               : "x" (horizontal scrollbar, Default) | "flex" (füllt verbleibenden Platz) | "none"
                               "flex" hat eine Mindesthöhe (--wd-table-min-h, Default 14rem ≈ Kopfzeile +
                               Leeranzeige) — bei zu niedrigem Viewport scrollt die Page-Shell statt dass
                               die Tabelle kollabiert. Pro Instanz überschreibbar via Klasse, z. B.
                               <x-table scroll="flex" class="[--wd-table-min-h:10rem]">.
      - tableSort            : "none" (Default) | "client" (data-sortable + JS) | "server" (Links)
      - route, currentSort, currentDir, sortParams : nur für tableSort="server" relevant; werden via @aware
                               an <x-table.th sort="…"> durchgereicht, das intern <x-sort-th> rendert
      - empty                : bool — erzwingt den Leerzustand (statt der Zeilen wird die Empty-State-Box
                               bzw. eine spannende Leerzeile gezeigt).
      - autoEmpty            : bool (Default true) — zeigt den Standard-Leerzustand AUTOMATISCH, wenn der
                               Zeilen-Slot nichts gerendert hat (z. B. `@foreach` ohne Treffer). Damit
                               bekommt jede Tabelle ohne eigenes `@empty` denselben Leerzustand.
                               Auf `false` setzen für Tabellen, deren Zeilen erst per JS gefüllt werden.
      - emptyTitle, emptyMessage, emptyIcon : an den Leerzustand durchgereicht (Defaults: „Keine Einträge
                               vorhanden" / „Für die aktuelle Auswahl wurden keine Daten gefunden." / inbox)

    Standardweg (auto): einfach `@foreach` verwenden — ist die Liste leer, rendert die Tabelle selbst
    den Leerzustand. Wer einen eigenen `@empty`-Text braucht, nutzt weiterhin `@forelse … @empty
    <x-table.empty …> @endforelse`; der gefüllte Slot deaktiviert die Auto-Erkennung.

    Beispiel:
        <x-table>
            <x-slot:head>
                <tr><x-table.th sort="name">Name</x-table.th></tr>
            </x-slot:head>
            @foreach ($customers as $c)
                <tr><td>{{ $c->name }}</td></tr>
            @endforeach
        </x-table>
--}}

@php
    $tableSort = in_array($tableSort, ['client', 'server', 'none'], true) ? $tableSort : 'none';

    $tableClasses = collect([
        'table',
        $size ? "table-{$size}" : null,
        $zebra ? 'table-zebra' : null,
        $pinRows ? 'table-pin-rows' : null,
    ])->filter()->implode(' ');

    $wrapperBase = match (true) {
        $bare && $scroll === 'flex'  => 'wd-table-flex min-h-(--wd-table-min-h) flex-1 overflow-hidden',
        $bare && $scroll === 'none'  => '',
        // Bare-Tabellen (in Cards) KEIN eigener overflow-Wrapper: der sonst
        // entstehende, ungebundene vertikale Scrollcontainer würde den
        // (via table-pin-rows) fixierten Kopf an sich binden statt an den
        // Seiten-Scrollport (<main> der wd-page-fill-Seite bzw. den Chart-
        // Scrollbereich). Horizontaler Überlauf läuft über <main> (overflow:auto).
        $bare                        => '',
        $scroll === 'flex'           => 'wd-table-flex min-h-(--wd-table-min-h) flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100',
        $scroll === 'none'           => 'rounded-box border border-base-300 bg-base-100 shadow-xs',
        default                      => 'overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-xs',
    };

    // Leerzustand: explizit erzwungen (empty=true) ODER automatisch, wenn der
    // Zeilen-Slot nichts gerendert hat. Views mit eigenem @empty/<x-table.empty>
    // füllen den Slot und lösen die Auto-Erkennung nicht aus.
    $isEmpty = $empty || ($autoEmpty && isset($slot) && $slot->isEmpty());

    // colspan der spannenden Leerzeile aus dem Head ableiten (Anzahl <th>).
    $emptyColspan = isset($head) ? max(1, (int) preg_match_all('/<th[\s>]/i', (string) $head)) : 1;

    $tableAttrs = $tableSort === 'client' ? ' data-sortable' : '';
@endphp

<div {{ $attributes->class([$wrapperBase]) }}>
    @if ($isEmpty && ! isset($head))
        {{-- Kein Tabellenkopf → reine Empty-State-Box --}}
        <div class="p-4">
            <x-empty-state :icon="$emptyIcon" :title="$emptyTitle" :message="$emptyMessage" tone="ghost" />
        </div>
    @elseif ($scroll === 'flex')
        <div class="h-full overflow-auto">
            <table class="{{ $tableClasses }}"{!! $tableAttrs !!}>
                @if ($caption)
                    <caption class="sr-only">{{ $caption }}</caption>
                @endif
                @isset($head)
                    <thead>{{ $head }}</thead>
                @endisset
                @isset($foot)
                    <tfoot>{{ $foot }}</tfoot>
                @endisset
                <tbody>
                    @if ($isEmpty)
                        <x-table.empty :colspan="$emptyColspan" :icon="$emptyIcon"
                                       :title="$emptyTitle" :message="$emptyMessage" compact />
                    @else
                        {{ $slot }}
                    @endif
                </tbody>
            </table>
        </div>
    @else
        <table class="{{ $tableClasses }}"{!! $tableAttrs !!}>
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif
            @isset($head)
                <thead>{{ $head }}</thead>
            @endisset
            @isset($foot)
                <tfoot>{{ $foot }}</tfoot>
            @endisset
            @isset($head)
                <tbody>
                    @if ($isEmpty)
                        <x-table.empty :colspan="$emptyColspan" :icon="$emptyIcon"
                                       :title="$emptyTitle" :message="$emptyMessage" compact />
                    @else
                        {{ $slot }}
                    @endif
                </tbody>
            @else
                {{-- Backwards-Kompatibilität: alte Aufrufe, die <thead>/<tbody> selbst
                     in den Default-Slot legen, weiterhin unterstützen. --}}
                {{ $slot }}
            @endif
        </table>
    @endif
</div>
