{{--
  Created on   : Tue May 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_strip.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    Geteilter Tab-Strip für Arbeitsliste & Kanban.

    Erwartet:
      - $tabs       array<string, array{label:string, count?:int, url?:string}>
      - $tab        string  aktueller Tab-Key
      - $tabFilters array   (optional) Filterwerte für Standardrouten zu duties.index
--}}
@php
    $tabFilters = $tabFilters ?? [];
@endphp
<div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
    @foreach ($tabs as $key => $info)
        <a role="tab"
           href="{{ $info['url'] ?? route('duties.index', array_merge($tabFilters, ['tab' => $key])) }}"
           class="tab {{ $tab === $key ? 'tab-active' : '' }}">
            {{ $info['label'] }}
            @isset($info['count'])
                <span class="badge badge-sm ml-2">{{ $info['count'] }}</span>
            @endisset
        </a>
    @endforeach
</div>
