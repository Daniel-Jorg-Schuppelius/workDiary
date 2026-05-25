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
