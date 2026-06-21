@props([
    'name',
    'label' => null,
    'checked' => false,
    'value' => '1',
    'toggle' => true,
    'tone' => 'primary',
    'span' => null,
    'error' => null,
    'hint' => null,
    'withHidden' => true,
])

@php
    $errorKey     = $error ?? $name;
    $hasError     = $errorKey && $errors->has($errorKey);
    $wrapperClass = 'fieldset' . ($span ? ' md:col-span-' . (int) $span : '');
    $controlBase  = $toggle ? 'toggle toggle-' . $tone : 'checkbox checkbox-' . $tone;
@endphp

<div class="{{ $wrapperClass }}">
    <label class="fieldset-label cursor-pointer gap-3">
        @if ($withHidden)
            <input type="hidden" name="{{ $name }}" value="0">
        @endif
        <input type="checkbox" name="{{ $name }}" value="{{ $value }}"
               {{ $attributes->class([$controlBase]) }}
               @checked($checked)>
        <span>{{ $label ?? $slot }}</span>
    </label>

    @if ($hint)
        <p class="text-xs text-base-content/60 mt-1">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="text-error text-sm">{{ $errors->first($errorKey) }}</p>
    @endif
</div>
