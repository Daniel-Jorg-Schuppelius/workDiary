{{--
    Route-basierte Tab-Navigation (Konsolidierung D5, Vorbild duties/_tab_strip).
    Nur für Server-Navigation — clientseitige (Alpine-)Tabs bleiben separat.

    items: Liste von Arrays mit
      label    Beschriftung (Pflicht)
      route    Routenname · params Routen-/Query-Parameter · href fertige URL statt route
      routeIs  Pattern oder Pattern-Array für die Aktiv-Erkennung (request()->routeIs)
      active   expliziter Aktiv-Bool (überschreibt routeIs)
      icon     Material-Symbol · count Badge-Zahl · when false blendet den Tab aus
--}}
@props(['items' => []])
<div role="tablist" {{ $attributes->merge(['class' => 'tabs tabs-box w-full']) }}>
    @foreach ($items as $item)
        @continue(($item['when'] ?? true) === false)
        @php
            $tabActive = $item['active']
                ?? (isset($item['routeIs']) && request()->routeIs(...(array) $item['routeIs']));
            $tabHref = $item['href'] ?? route($item['route'], $item['params'] ?? []);
        @endphp
        <a role="tab" href="{{ $tabHref }}"
           @class(['tab gap-1', 'tab-active' => $tabActive])
           @if ($tabActive) aria-current="page" @endif>
            @if (! empty($item['icon']))
                <span class="material-symbols-outlined text-base" aria-hidden="true">{{ $item['icon'] }}</span>
            @endif
            {{ $item['label'] }}
            @if (isset($item['count']))
                <span class="badge badge-sm ml-2">{{ $item['count'] }}</span>
            @endif
        </a>
    @endforeach
</div>
