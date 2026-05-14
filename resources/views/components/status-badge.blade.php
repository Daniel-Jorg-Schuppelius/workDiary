@props([
    'tone' => 'ghost',
    'size' => 'sm',
    'label' => null,
    'outline' => false,
])

@php
    $tones = ['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'info', 'ghost', 'neutral'];
    $tone = in_array($tone, $tones, true) ? $tone : 'ghost';
    $sizes = ['xs', 'sm', 'md', 'lg'];
    $size = in_array($size, $sizes, true) ? $size : 'sm';
    $classes = trim(implode(' ', array_filter([
        'badge',
        'badge-'.$size,
        $outline ? 'badge-outline' : 'badge-'.$tone,
    ])));
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $label ?? $slot }}
</span>
