{{--
    Gemeinsame Ansicht-Umschaltung für Touren: Liste <-> Karte.
    Übernimmt die aktuellen Filter-Query-Parameter (from/to/status/user …),
    damit der Zeitraum/Filter beim Wechsel erhalten bleibt.
--}}
@php
    $tabQuery = request()->query();
@endphp

<div role="tablist" class="tabs tabs-box w-full">
    <a role="tab"
       href="{{ route('tours.index', $tabQuery) }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('tours.index')])
       @if (request()->routeIs('tours.index')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">list</span>
        {{ __('Liste') }}
    </a>
    <a role="tab"
       href="{{ route('tours.map', $tabQuery) }}"
       @class(['tab gap-1', 'tab-active' => request()->routeIs('tours.map')])
       @if (request()->routeIs('tours.map')) aria-current="page" @endif>
        <span class="material-symbols-outlined text-base" aria-hidden="true">map</span>
        {{ __('Karte') }}
    </a>
</div>
