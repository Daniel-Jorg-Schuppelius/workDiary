{{-- Tageszelle des Verfügbarkeitskalenders: Belegungsfenster als Badges --}}
@php($kindTones = ['soft' => 'badge-ghost', 'hard' => 'badge-info', 'rental' => 'badge-primary', 'maintenance' => 'badge-warning', 'cleaning' => 'badge-accent', 'transport' => 'badge-neutral'])
<div class="flex flex-col gap-0.5">
    @foreach ($items as $item)
        @php($tone = $kindTones[$item->kind->value] ?? 'badge-ghost')
        <a @if ($item->rentalCase !== null) href="{{ route('rental.show', $item->rentalCase) }}" @endif
           class="badge {{ $tone }} badge-sm block w-full truncate text-left"
           title="{{ $item->asset->name ?? '' }} — {{ $item->kind->label() }}{{ $item->rentalCase !== null ? ' (' . $item->rentalCase->number . ')' : '' }}">
            {{ $item->asset->name ?? '—' }}
        </a>
    @endforeach
</div>
