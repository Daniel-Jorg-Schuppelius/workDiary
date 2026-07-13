@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 2,
    'required' => false,
    'span' => null,
    'error' => null,
    'hint' => null,
])

@php
    $errorKey     = $error ?? $name;
    $hasError     = $errorKey && $errors->has($errorKey);
    $controlError = $hasError ? 'textarea-error' : null;
    $wrapperClass = 'fieldset' . ($span ? ' md:col-span-' . (int) $span : '');

    // Barrierefreiheit: Hilfetext/Fehler programmatisch mit dem Feld verknüpfen.
    $hintId       = $hint ? $name . '-hint' : null;
    $errorId      = $hasError ? $name . '-error' : null;
    $describedBy  = implode(' ', array_filter([$hintId, $errorId])) ?: null;
@endphp

<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="fieldset-label" for="{{ $name }}">{{ $label }}@if ($required) *@endif</label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required aria-required="true" @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class(array_filter(['textarea textarea-bordered', 'w-full', $controlError])) }}
    >{{ trim($slot) !== '' ? $slot : $value }}</textarea>

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-base-content/60 mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $errorId }}" class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
