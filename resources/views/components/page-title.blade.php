@props([
    'title' => null,
    'subtitle' => null,
    'badge' => null,
    'badgeTone' => 'primary',
])

<header {{ $attributes->class(['flex flex-wrap items-start justify-between gap-3']) }}>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="font-['Space_Grotesk'] text-3xl font-semibold leading-tight text-base-content">
                {{ $title ?? $slot }}
            </h1>
            @if ($badge)
                <span class="badge badge-sm badge-{{ $badgeTone }}">{{ $badge }}</span>
            @endif
        </div>
        @if ($subtitle)
            <p class="mt-1 text-sm text-base-content/70">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</header>
