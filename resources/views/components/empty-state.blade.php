@props([
    'icon' => null,
    'title' => null,
    'message' => null,
    'tone' => 'ghost',
])

@php
    $toneClass = [
        'primary' => 'border-primary/30 bg-primary/5 text-primary',
        'success' => 'border-success/30 bg-success/5 text-success',
        'warning' => 'border-warning/30 bg-warning/5 text-warning',
        'error'   => 'border-error/30 bg-error/5 text-error',
        'info'    => 'border-info/30 bg-info/5 text-info',
        'ghost'   => 'border-base-300 bg-base-200/40 text-base-content/70',
    ][$tone] ?? 'border-base-300 bg-base-200/40 text-base-content/70';
@endphp

<div {{ $attributes->merge(['class' => "wd-empty-state flex flex-col items-center justify-center gap-3 rounded-box border border-dashed px-6 py-10 text-center {$toneClass}"]) }}>
    @if ($icon)
        <div class="text-3xl opacity-70">
            {!! $icon !!}
        </div>
    @endif

    @if ($title)
        <h3 class="font-['Space_Grotesk'] text-lg font-bold text-base-content">{{ $title }}</h3>
    @endif

    @if ($message)
        <p class="max-w-prose text-sm text-base-content/70">{{ $message }}</p>
    @endif

    @isset($action)
        <div class="mt-2">
            {{ $action }}
        </div>
    @endisset

    {{ $slot }}
</div>
