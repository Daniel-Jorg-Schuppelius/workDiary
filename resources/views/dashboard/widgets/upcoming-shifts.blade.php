<x-card :title="__('Anstehende Schichten')">
    @if ($today->isNotEmpty())
        <p class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Heute') }}</p>
        <ul class="mt-1 space-y-1 text-sm">
            @foreach ($today as $shift)
                <li class="flex items-center gap-2">
                    <x-icon name="event" />
                    <span>{{ $shift->start_at->format('H:i') }} – {{ $shift->end_at->format('H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($shifts->isNotEmpty())
        <p class="mt-3 text-xs uppercase tracking-wider text-base-content/60">{{ __('Kommende Tage') }}</p>
        <ul class="mt-1 space-y-1 text-sm">
            @foreach ($shifts as $shift)
                <li class="flex items-center gap-2">
                    <x-icon name="schedule" />
                    <span>{{ $shift->start_at->format('d.m. H:i') }} – {{ $shift->end_at->format('d.m. H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($today->isEmpty() && $shifts->isEmpty())
        <p class="text-sm text-base-content/60">{{ __('Keine anstehenden Schichten.') }}</p>
    @endif
</x-card>
