@props([
    'column',
    'route',
    'params' => [],
    'sort' => null,
    'dir' => 'desc',
    'default' => null, // wenn aktueller sort leer/equiv ist und default==column, Icon trotzdem anzeigen
])

@php
    $current = (string) ($sort ?? '');
    $currentDir = strtolower((string) ($dir ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    $isActive = $current === $column || ($current === '' && $default === $column);
    $nextDir = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
    $icon = $isActive ? ($currentDir === 'asc' ? '↑' : '↓') : '↕';
    $params = is_array($params) ? $params : [];
    $href = $route . (str_contains($route, '?') ? '&' : '?') . http_build_query(array_merge(
        array_filter($params, fn ($v) => $v !== null && $v !== ''),
        ['sort' => $column, 'dir' => $nextDir],
    ));
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'link link-hover whitespace-nowrap']) }}>
    {{ $slot }}
    <span @class([
        'ml-1',
        'text-base-content/40' => ! $isActive,
        'text-base-content' => $isActive,
    ])>{!! $icon !!}</span>
</a>
