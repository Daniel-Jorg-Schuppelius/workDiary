{{--
  Created on   : Sat Jul 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : tab-nav.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
@php
    // w-full nur als Default: Aufrufer mit eigener Breitenklasse (w-fit,
    // w-auto, …) bekommen keinen w-full-Konflikt (Vollaudit 2026-07, N44).
    $tabNavHasWidth = preg_match('/(^|\s)w-\S+/', (string) $attributes->get('class', '')) === 1;
@endphp
<div role="tablist" {{ $attributes->merge(['class' => 'tabs tabs-box' . ($tabNavHasWidth ? '' : ' w-full')]) }}>
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
