{{--
  Created on   : Mon May 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : th.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@aware([
    'tableSort'   => 'none',
    'route'       => null,
    'currentSort' => null,
    'currentDir'  => 'desc',
    'sortParams'  => [],
])

@props([
    'sort'    => null,    // bool (client) | string column-key (server) | null
    'type'    => null,    // string|number|date|duration (client only)
    'default' => null,    // asc|desc (client only); für server: nicht-null markiert Default-Spalte
    'align'   => null,    // left|right|center
    'scope'   => 'col',   // Barrierefreiheit: Spaltenkopf (Default) | 'row' für Zeilenkopf | '' zum Weglassen
])

@php
    /**
     * Sub-component für <x-table>-Header. Verhalten richtet sich nach dem
     * `tableSort`-Modus des umschließenden <x-table>:
     *
     *   - tableSort="client"  → setzt data-sort/data-sort-type für sortable-tables.js
     *   - tableSort="server"  → rendert intern <x-sort-th> (Link mit ?sort=col&dir=…)
     *   - tableSort="none"    → einfaches <th>
     *
     * In client/none Modus ist die `sort`-Prop ein Bool; in server Modus ein
     * Spaltenname (String).
     */
    $alignClass = match ($align) {
        'right'  => 'text-right',
        'center' => 'text-center',
        default  => null,
    };
@endphp

@if ($tableSort === 'server' && is_string($sort) && $sort !== '')
    <th @if ($scope) scope="{{ $scope }}" @endif {{ $attributes->class([$alignClass])->except(['sort', 'type', 'default', 'align', 'scope']) }}>
        <x-sort-th
            :column="$sort"
            :route="$route"
            :params="$sortParams"
            :sort="$currentSort"
            :dir="$currentDir"
            :default="$default ? $sort : null"
        >{{ $slot }}</x-sort-th>
    </th>
@elseif ($tableSort === 'client' && $sort)
    <th
        @if ($scope) scope="{{ $scope }}" @endif
        data-sort
        @if ($type) data-sort-type="{{ $type }}" @endif
        @if ($default) data-sort-default="{{ $default }}" @endif
        {{ $attributes->class([$alignClass])->except(['sort', 'type', 'default', 'align', 'scope']) }}
    >{{ $slot }}</th>
@else
    <th @if ($scope) scope="{{ $scope }}" @endif {{ $attributes->class([$alignClass])->except(['sort', 'type', 'default', 'align', 'scope']) }}>{{ $slot }}</th>
@endif
