@props([
    'size'         => 'sm',
    'zebra'        => true,
    'pinRows'      => false,
    'scroll'       => 'x',
    'empty'        => false,
    'emptyTitle'   => null,
    'emptyMessage' => null,
    'emptyIcon'    => null,
    'emptyColspan' => null,
])

{{--
    <x-table> — kanonische Tabellen-Karte für den neuen Bereich.

    Props:
      - size, zebra, pinRows : DaisyUI-Tabellenoptionen
      - scroll               : "x" (horizontal scrollbar) | "flex" (füllt verbleibenden Platz)
      - empty                : bool — wenn true, wird statt der Tabelle ein <x-empty-state>
                               in einer grauen Box innerhalb der weißen Karte angezeigt.
                               (Alternativ kann der konsumierende Code @forelse/@empty in
                               <tbody> selbst verwenden.)
      - emptyTitle, emptyMessage, emptyIcon : an <x-empty-state> durchgereicht
--}}

@php
    $tableClasses = collect([
        'table',
        $size ? "table-{$size}" : null,
        $zebra ? 'table-zebra' : null,
        $pinRows ? 'table-pin-rows' : null,
    ])->filter()->implode(' ');

    $wrapperBase = $scroll === 'flex'
        ? 'min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100'
        : 'overflow-x-auto rounded-box border border-base-300 bg-base-100 shadow-xs';
@endphp

<div {{ $attributes->class([$wrapperBase]) }}>
    @if ($empty)
        <div class="p-4">
            <x-empty-state
                :icon="$emptyIcon"
                :title="$emptyTitle ?? __('Keine Daten vorhanden')"
                :message="$emptyMessage"
                tone="ghost"
            />
        </div>
    @elseif ($scroll === 'flex')
        <div class="h-full overflow-auto">
            <table class="{{ $tableClasses }}">
                {{ $slot }}
            </table>
        </div>
    @else
        <table class="{{ $tableClasses }}">
            {{ $slot }}
        </table>
    @endif
</div>
