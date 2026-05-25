<x-card :title="__('Aktuelle Notdienste')">
    @if ($emergencies->isNotEmpty())
        <ul class="space-y-1 text-sm">
            @foreach ($emergencies as $em)
                <li class="flex items-center gap-2">
                    <x-icon name="priority_high" />
                    <span>{{ $em->start_at->format('d.m. H:i') }} – {{ $em->end_at->format('d.m. H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-sm text-base-content/60">{{ __('Keine anstehenden Notdienste.') }}</p>
    @endif
</x-card>
