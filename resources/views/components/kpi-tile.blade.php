@props([
    'label' => '',
    'value' => 0,
    'tone' => 'neutral',
    'hint' => null,
    'href' => null,
    'active' => false,
    'format' => 'int',
    // Glossar-Key (Feature 039): hängt einen Begriffs-Tooltip ans Label.
    'term' => null,
])

@php
    $borderTone = [
        'primary'   => 'border-primary/40',
        'info'      => 'border-info/40',
        'success'   => 'border-success/40',
        'warning'   => 'border-warning/40',
        'error'     => 'border-error/40',
        'secondary' => 'border-secondary/40',
        'neutral'   => 'border-base-300',
    ][$tone] ?? 'border-base-300';

    $valueTone = [
        'primary'   => 'text-base-content',
        'info'      => 'text-info',
        'success'   => 'text-success',
        'warning'   => 'text-warning',
        'error'     => 'text-error',
        'secondary' => 'text-secondary',
        'neutral'   => 'text-base-content',
    ][$tone] ?? 'text-base-content';

    $base = 'rounded-box border bg-base-100 px-4 py-3 shadow-xs ' . $borderTone;
    if ($active) {
        $base .= ' border-primary ring-1 ring-primary/40';
    }
    if ($href) {
        $base .= ' transition hover:border-primary hover:shadow-md';
    }

    if ($format === 'int' && is_numeric($value)) {
        $displayValue = number_format((float) $value, 0, ',', '.');
    } elseif ($format === 'decimal' && is_numeric($value)) {
        $displayValue = number_format((float) $value, 1, ',', '.');
    } else {
        $displayValue = (string) $value;
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base]) }}>
        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60"><x-term :glossary="$term">{{ $label }}</x-term></p>
        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold {{ $valueTone }}">{{ $displayValue }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-base-content/55">{{ $hint }}</p>
        @endif
    </a>
@else
    <div {{ $attributes->class([$base]) }}>
        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60"><x-term :glossary="$term">{{ $label }}</x-term></p>
        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold {{ $valueTone }}">{{ $displayValue }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-base-content/55">{{ $hint }}</p>
        @endif
    </div>
@endif
