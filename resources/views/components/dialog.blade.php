@props([
    'title' => null,
    'eyebrow' => null,
    'icon' => null,
    'badge' => null,
    'badgeTone' => 'ghost',
    'tone' => 'primary',
])

@php
    $accent = [
        'primary' => 'from-primary/15 via-primary/5 to-transparent',
        'success' => 'from-success/15 via-success/5 to-transparent',
        'warning' => 'from-warning/15 via-warning/5 to-transparent',
        'error'   => 'from-error/15 via-error/5 to-transparent',
        'info'    => 'from-info/15 via-info/5 to-transparent',
        'ghost'   => 'from-base-300/40 via-base-200/30 to-transparent',
    ][$tone] ?? 'from-primary/15 via-primary/5 to-transparent';

    $iconAccent = [
        'primary' => 'bg-primary/15 text-primary',
        'success' => 'bg-success/15 text-success',
        'warning' => 'bg-warning/15 text-warning',
        'error'   => 'bg-error/15 text-error',
        'info'    => 'bg-info/15 text-info',
        'ghost'   => 'bg-base-300 text-base-content/70',
    ][$tone] ?? 'bg-primary/15 text-primary';
@endphp

<div {{ $attributes->merge(['class' => "wd-dialog"]) }}>
    {{-- Header --}}
    @if ($title || $eyebrow || $icon || $badge || isset($header))
        <header class="wd-dialog__header sticky top-0 z-10 flex items-start gap-3 border-b border-base-300 bg-linear-to-br {{ $accent }} px-6 py-5 pr-14">
            @if ($icon)
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-box {{ $iconAccent }} text-lg">
                    {!! $icon !!}
                </div>
            @endif
            <div class="min-w-0 flex-1">
                @if ($eyebrow)
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-base-content/60">{{ $eyebrow }}</p>
                @endif
                @if ($title)
                    <h2 class="font-['Space_Grotesk'] text-xl font-bold text-base-content @if($eyebrow) mt-1 @endif">{{ $title }}</h2>
                @endif
                @isset($header)
                    {{ $header }}
                @endisset
            </div>
            @if ($badge)
                <span class="badge badge-sm badge-{{ $badgeTone }} shrink-0">{{ $badge }}</span>
            @endif
            <button type="button" data-entry-modal-close
                class="absolute right-4 top-4 btn btn-sm btn-ghost btn-circle"
                aria-label="{{ __('Schließen') }}">✕</button>
        </header>
    @endif

    {{-- Body --}}
    <div class="wd-dialog__body px-6 py-5">
        {{ $slot }}
    </div>

    {{-- Footer (optional) --}}
    @isset($actions)
        <footer class="wd-dialog__footer sticky bottom-0 flex flex-wrap items-center justify-end gap-2 border-t border-base-300 bg-base-100 px-6 py-3">
            {{ $actions }}
        </footer>
    @endisset
</div>
