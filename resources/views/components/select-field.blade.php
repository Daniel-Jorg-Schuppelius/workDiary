@props([
    'name',
    'label' => null,
    'required' => false,
    'span' => null,
    'error' => null,
    'hint' => null,
])

@php
    $errorKey     = $error ?? $name;
    $hasError     = $errorKey && $errors->has($errorKey);
    $controlError = $hasError ? 'select-error' : null;
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

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        @if ($required) required aria-required="true" @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class(array_filter(['select select-bordered', 'w-full', $controlError])) }}
    >{{ $slot }}</select>

    @if ($hint)
        <p id="{{ $hintId }}" class="text-xs text-base-content/60 mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p id="{{ $errorId }}" class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
