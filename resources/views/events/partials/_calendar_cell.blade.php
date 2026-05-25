{{--
    Tag-Zellen-Renderer für <x-month-calendar item-view="events.partials._calendar_cell" />.
    Erhält pro Tag: $day (CarbonImmutable), $items (Collection<Event>), $isOther, $isToday.
--}}
@foreach ($items as $event)
    <a href="{{ route('events.show', $event) }}"
       class="block truncate rounded px-1 py-0.5 text-xs text-white"
       style="background:{{ $event->category?->color ?? '#3b82f6' }}"
       title="{{ $event->title }} – {{ $event->started_at?->isoFormat('HH:mm') }}">
        <strong>{{ $event->started_at?->format('H:i') }}</strong>
        {{ $event->title }}
    </a>
@endforeach
