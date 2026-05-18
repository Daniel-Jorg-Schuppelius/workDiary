@props([
    'size'         => 'sm',
    'zebra'        => true,
    'pinRows'      => false,
    'scroll'       => 'x',
    'tableSort'    => 'none',  // none | client | server
    'route'        => null,    // nur sort=server
    'currentSort'  => null,    // nur sort=server (aktueller Sort-Schlüssel)
    'currentDir'   => 'desc',  // nur sort=server
    'sortParams'   => [],      // nur sort=server (zusätzliche Query-Parameter)
    'bare'         => false,   // wenn true: kein border/rounded-box/shadow am Wrapper
                               // (z.B. wenn die Tabelle bereits in einer Card mit Header sitzt)
    'empty'        => false,
    'emptyTitle'   => null,
    'emptyMessage' => null,
    'emptyIcon'    => null,
])

{{--
    <x-table> — kanonische Tabellen-Karte.

    Props:
      - size, zebra, pinRows : DaisyUI-Tabellenoptionen
      - scroll               : "x" (horizontal scrollbar, Default) | "flex" (füllt verbleibenden Platz) | "none"
      - tableSort            : "none" (Default) | "client" (data-sortable + JS) | "server" (Links)
      - route, currentSort, currentDir, sortParams : nur für tableSort="server" relevant; werden via @aware
                               an <x-table.th sort="…"> durchgereicht, das intern <x-sort-th> rendert
      - empty                : bool — wenn true, wird statt der Tabelle direkt eine <x-empty-state>-
                               Box angezeigt (für Fälle, in denen das ganze Datenset leer ist und
                               der Header gar nicht gezeigt werden soll). Standardweg ist
                               @forelse / @empty mit <x-table.empty> als Zeile.
      - emptyTitle, emptyMessage, emptyIcon : an <x-empty-state> durchgereicht

    Beispiel:
        <x-table table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">Name</x-table.th>
                    <x-table.th sort type="date">Datum</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($items as $item)
                <tr>...</tr>
            @empty
                <x-table.empty colspan="2" icon="inbox" :title="__('Keine Einträge')" />
            @endforelse
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
        $bare && $scroll === 'flex'  => 'min-h-0 flex-1 overflow-hidden',
        $bare && $scroll === 'none'  => '',
        $bare                        => 'overflow-x-auto',
        $scroll === 'flex'           => 'min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100',
        $scroll === 'none'           => 'rounded-box border border-base-300 bg-base-100 shadow-xs',
        default                      => 'overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-xs',
    };

    // Default-Empty-Icon, falls keins gesetzt ist
    $emptyIconDefault = $emptyIcon ?? '<span class="material-symbols-outlined" aria-hidden="true">inbox</span>';
@endphp

<div {{ $attributes->class([$wrapperBase]) }}>
    @if ($empty)
        <div class="p-4">
            <x-empty-state
                :icon="$emptyIconDefault"
                :title="$emptyTitle ?? __('Keine Daten vorhanden')"
                :message="$emptyMessage"
                tone="ghost"
            />
        </div>
    @else
        @php
            $tableAttrs = $tableSort === 'client' ? ' data-sortable' : '';
        @endphp

        @if ($scroll === 'flex')
            <div class="h-full overflow-auto">
                <table class="{{ $tableClasses }}"{!! $tableAttrs !!}>
                    @isset($head)
                        <thead>{{ $head }}</thead>
                    @endisset
                    @isset($foot)
                        <tfoot>{{ $foot }}</tfoot>
                    @endisset
                    <tbody>{{ $slot }}</tbody>
                </table>
            </div>
        @else
            <table class="{{ $tableClasses }}"{!! $tableAttrs !!}>
                @isset($head)
                    <thead>{{ $head }}</thead>
                @endisset
                @isset($foot)
                    <tfoot>{{ $foot }}</tfoot>
                @endisset
                @isset($head)
                    <tbody>{{ $slot }}</tbody>
                @else
                    {{-- Backwards-Kompatibilität: alte Aufrufe, die <thead>/<tbody> selbst
                         in den Default-Slot legen, weiterhin unterstützen. --}}
                    {{ $slot }}
                @endisset
            </table>
        @endif
    @endif
</div>
