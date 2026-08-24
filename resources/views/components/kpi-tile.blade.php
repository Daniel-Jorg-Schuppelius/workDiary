{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : kpi-tile.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
        $displayValue = \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $value, 0, withThousandsSeparator: true);
    } elseif ($format === 'decimal' && is_numeric($value)) {
        $displayValue = \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $value, 1, withThousandsSeparator: true);
    } else {
        $displayValue = (string) $value;
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base]) }}>
        <p class="text-xs uppercase tracking-[0.18em] text-muted"><x-term :glossary="$term">{{ $label }}</x-term></p>
        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold {{ $valueTone }}">{{ $displayValue }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-base-content/55">{{ $hint }}</p>
        @endif
    </a>
@else
    <div {{ $attributes->class([$base]) }}>
        <p class="text-xs uppercase tracking-[0.18em] text-muted"><x-term :glossary="$term">{{ $label }}</x-term></p>
        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold {{ $valueTone }}">{{ $displayValue }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-base-content/55">{{ $hint }}</p>
        @endif
    </div>
@endif
