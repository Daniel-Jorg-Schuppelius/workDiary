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
