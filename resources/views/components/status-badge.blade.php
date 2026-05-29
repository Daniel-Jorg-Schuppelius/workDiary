@props([
    'tone' => 'ghost',
    'size' => 'sm',
    'label' => null,
    'outline' => false,
    'icon' => null,
])

@php
    $tones = ['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'info', 'ghost', 'neutral'];
    $tone = in_array($tone, $tones, true) ? $tone : 'ghost';
    $sizes = ['xs', 'sm', 'md', 'lg'];
    $size = in_array($size, $sizes, true) ? $size : 'sm';
    $classes = trim(implode(' ', array_filter([
        'badge',
        'badge-'.$size,
        'badge-'.$tone,
        $outline ? 'badge-outline' : null,
    ])));
    $iconIsSymbol = is_string($icon) && $icon !== '' && preg_match('/^[a-z0-9_]+$/', $icon) === 1;
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($icon)
        @if ($iconIsSymbol)
            <x-icon :name="$icon" size="1em" />
        @else
            {!! $icon !!}
        @endif
    @endif
    {{ $label ?? $slot }}
</span>
