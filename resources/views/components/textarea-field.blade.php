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
@endphp

<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="fieldset-label" for="{{ $name }}">{{ $label }}@if ($required) *@endif</label>
    @endif

    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        {{ $attributes->class(array_filter(['textarea textarea-bordered', 'w-full', $controlError])) }}
    >{{ trim($slot) !== '' ? $slot : $value }}</textarea>

    @if ($hint)
        <p class="text-xs text-base-content/60 mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
