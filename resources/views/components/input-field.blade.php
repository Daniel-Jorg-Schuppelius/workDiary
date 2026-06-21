@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'span' => null,
    'error' => null,
    'hint' => null,
])

@php
    $errorKey      = $error ?? $name;
    $hasError      = $errorKey && $errors->has($errorKey);
    $isFile        = $type === 'file';
    $controlBase   = $isFile ? 'file-input file-input-bordered' : 'input input-bordered';
    $controlError  = $hasError ? ($isFile ? 'file-input-error' : 'input-error') : null;
    $wrapperClass  = 'fieldset' . ($span ? ' md:col-span-' . (int) $span : '');
@endphp

<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="fieldset-label" for="{{ $name }}">{{ $label }}@if ($required) *@endif</label>
    @endif

    @if (trim($slot) !== '')
        {{ $slot }}
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            @if ($required) required @endif
            @unless ($isFile) value="{{ $value }}" @endunless
            {{ $attributes->class(array_filter([$controlBase, 'w-full', $controlError])) }}
        >
    @endif

    @if ($hint)
        <p class="text-xs text-base-content/60 mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
