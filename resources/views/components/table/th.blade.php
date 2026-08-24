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

    // Barrierefreiheit (I3, WCAG 1.3.1): aria-sort serverseitig aus der
    // aktuellen Sortierung ableiten — ascending/descending an der aktiven
    // Spalte, none an den übrigen sortierbaren Spalten.
    $dirNorm = strtolower((string) ($currentDir ?? 'desc')) === 'asc' ? 'ascending' : 'descending';
    $serverActive = $tableSort === 'server' && is_string($sort) && $sort !== ''
        && ($currentSort === $sort || (($currentSort === null || $currentSort === '') && $default !== null));
    $serverAriaSort = $serverActive ? $dirNorm : 'none';
    // Client-Modus: Initialzustand aus data-sort-default; sortable-tables.js
    // hält aria-sort danach synchron.
    $clientAriaSort = $default ? ($default === 'desc' ? 'descending' : 'ascending') : 'none';
@endphp

@if ($tableSort === 'server' && is_string($sort) && $sort !== '')
    <th @if ($scope) scope="{{ $scope }}" @endif aria-sort="{{ $serverAriaSort }}" {{ $attributes->class([$alignClass])->except(['sort', 'type', 'default', 'align', 'scope']) }}>
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
        aria-sort="{{ $clientAriaSort }}"
        @if ($type) data-sort-type="{{ $type }}" @endif
        @if ($default) data-sort-default="{{ $default }}" @endif
        {{ $attributes->class([$alignClass])->except(['sort', 'type', 'default', 'align', 'scope']) }}
    >{{-- Echter Button (I3): Tastatur-Sortierung — Enter/Space lösen den
         Klick-Handler von sortable-tables.js aus (bubbelt zum <th>). --}}<button type="button" class="cursor-pointer select-none">{{ $slot }}</button></th>
@else
    <th @if ($scope) scope="{{ $scope }}" @endif {{ $attributes->class([$alignClass])->except(['sort', 'type', 'default', 'align', 'scope']) }}>{{ $slot }}</th>
@endif
