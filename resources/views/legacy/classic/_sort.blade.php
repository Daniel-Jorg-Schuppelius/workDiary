{{--
  Created on   : Sun Nov 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _sort.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Sortierbarer Spaltenkopf wie im Altportal (»Spalte« = aktiv). Erwartet: $route, $key, $label, $sort, $dir; optional $params, $routeParams, $colspan, $width --}}
@php
    $isActive = $sort === $key;
    $nextDir = $isActive && $dir === 'asc' ? 'desc' : 'asc';
    $href = route($route, array_merge($routeParams ?? [], $params ?? [], ['sort' => $key, 'dir' => $nextDir]));
@endphp
<th @isset($colspan) colspan="{{ $colspan }}" @endisset @isset($width) style="width: {{ $width }}" @endisset @if ($isActive) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    @if ($isActive)&raquo;@endif<a href="{{ $href }}">{{ $label }}</a>@if ($isActive)&laquo;@endif
</th>
