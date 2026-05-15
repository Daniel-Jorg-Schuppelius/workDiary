@props([
    'label' => null,
    'for' => null,
    'class' => '',
])

<div class="flex flex-col gap-1 {{ $class }}">
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif
               class="text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/60">
            {{ $label }}
        </label>
    @endif
    {{ $slot }}
</div>
