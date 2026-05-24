@props([
    'topic',
    'label' => null,
    'size' => 'sm',
])

@php
    $resolvedLabel = $label ?? __('Hilfe');
    $sizeClass = match ($size) {
        'xs' => 'btn-xs',
        'md' => 'btn-md',
        default => 'btn-sm',
    };
@endphp

<button type="button"
        class="btn btn-ghost {{ $sizeClass }} text-base-content/70"
        data-help-trigger
        data-help-topic="{{ $topic }}"
        title="{{ $resolvedLabel }}"
        aria-label="{{ $resolvedLabel }}">
    <x-icon name="help_outline" />
    <span class="sr-only">{{ $resolvedLabel }}</span>
</button>
