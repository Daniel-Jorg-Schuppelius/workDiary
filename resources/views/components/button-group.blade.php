{{--
  Created on   : Fri May 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : button-group.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'gap' => 2,
    'wrap' => true,
    'center' => false,
])

@php
    $gap     = in_array((int) $gap, [1, 2, 3], true) ? (int) $gap : 2;
    $classes = array_filter([
        'flex items-center',
        'gap-' . $gap,
        $wrap ? 'flex-wrap' : null,
        $center ? 'justify-center' : null,
    ]);
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
