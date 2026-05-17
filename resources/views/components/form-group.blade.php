@props([
    'legend' => null,
    'description' => null,
    'icon' => null,
    'tone' => 'ghost',
    'cols' => 1,
    'compact' => false,
])

@php
    $toneClass = in_array($tone, ['primary', 'success', 'warning', 'error', 'info', 'ghost'], true)
        ? 'wd-fieldset--' . $tone
        : 'wd-fieldset--ghost';

    $cols       = (int) $cols;
    $cols       = in_array($cols, [1, 2, 3], true) ? $cols : 1;
    $colsClass  = 'wd-fieldset__grid--cols-' . $cols;
    $compactCls = $compact ? ' wd-fieldset--compact' : '';
    $iconIsSymbol = is_string($icon) && $icon !== '' && preg_match('/^[a-z0-9_]+$/', $icon) === 1;
@endphp

<fieldset {{ $attributes->merge(['class' => 'wd-fieldset ' . $toneClass . $compactCls]) }}>
    @if ($legend)
        <legend class="wd-fieldset__legend">
            @if ($icon)
                <span class="wd-fieldset__legend-icon" aria-hidden="true">
                    @if ($iconIsSymbol)
                        <x-icon :name="$icon" />
                    @else
                        {!! $icon !!}
                    @endif
                </span>
            @endif
            <span>{{ $legend }}</span>
        </legend>
    @endif

    @if ($description)
        <p class="wd-fieldset__description">{{ $description }}</p>
    @endif

    <div class="wd-fieldset__grid {{ $colsClass }}">
        {{ $slot }}
    </div>
</fieldset>
