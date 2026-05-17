{{--
    Created on   : Sun May 17 2026
    Author       : Daniel Jörg Schuppelius
    Author Uri   : https://schuppelius.org
    Filename     : map.blade.php
    License      : AGPL-3.0-or-later
    License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

    Usage:
        <x-map :markers="$markers" :route="$route" height="320px" />
        <x-map :center="[52.52, 13.405]" zoom="11" />

    Loads `resources/js/map.js` via @once @push('scripts').
--}}
@props([
    'markers' => [],
    'route' => null,
    'center' => null,
    'zoom' => 13,
    'height' => '320px',
    'id' => null,
])

@php
    $mapId = $id ?: 'wd-map-' . \Illuminate\Support\Str::random(6);
    $config = [
        'tiles' => [
            'url' => config('routing.tiles.url'),
            'attribution' => config('routing.tiles.attribution'),
            'maxZoom' => (int) config('routing.tiles.max_zoom', 19),
        ],
        'center' => $center,
        'zoom' => (int) $zoom,
        'markers' => array_values(array_map(static fn ($m) => [
            'lat' => (float) ($m['lat'] ?? $m[0] ?? 0),
            'lng' => (float) ($m['lng'] ?? $m[1] ?? 0),
            'label' => $m['label'] ?? null,
        ], $markers ?? [])),
        'route' => $route,
    ];
@endphp

<div
    id="{{ $mapId }}"
    data-map
    data-config="{{ json_encode($config, JSON_THROW_ON_ERROR) }}"
    style="height: {{ $height }};"
    {{ $attributes->merge(['class' => 'rounded-box border border-base-300 overflow-hidden']) }}
></div>

@once
    @push('scripts')
        @vite('resources/js/map.js')
    @endpush
@endonce
