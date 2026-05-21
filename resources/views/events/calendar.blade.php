@extends('layouts.app')
@section('title', __('Veranstaltungs-Kalender'))
@section('nav-title', __('Veranstaltungs-Kalender'))

@php
    use Carbon\Carbon;

    /** @var Carbon $monthStart */
    /** @var Carbon $monthEnd */
    /** @var \Illuminate\Support\Collection $eventsByDay */ // key = Y-m-d
    /** @var Carbon $cursor */
    $weekDays = [__('Mo'), __('Di'), __('Mi'), __('Do'), __('Fr'), __('Sa'), __('So')];

    $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
    $gridEnd   = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

    $days = [];
    for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
        $days[] = $d->copy();
    }

    $prev = $monthStart->copy()->subMonthNoOverflow()->startOfMonth();
    $next = $monthStart->copy()->addMonthNoOverflow()->startOfMonth();
@endphp

@section('content')
    <x-page-shell gap="6">
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <x-icon-btn icon="chevron_left" tone="ghost" size="sm"
                                :href="route('events.calendar', ['month' => $prev->format('Y-m')])"
                                :label="__('Vormonat')" />
                    <span class="font-semibold px-2">{{ $monthStart->isoFormat('MMMM YYYY') }}</span>
                    <x-icon-btn icon="chevron_right" tone="ghost" size="sm"
                                :href="route('events.calendar', ['month' => $next->format('Y-m')])"
                                :label="__('Folgemonat')" />
                    <x-icon-btn icon="today" tone="ghost" size="sm" :href="route('events.calendar')" :label="__('Heute')" />
                    <x-icon-btn icon="list" tone="ghost" size="sm" :href="route('events.index')" show-label>{{ __('Liste') }}</x-icon-btn>
                    @can('create', App\Models\Event::class)
                        <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('events.create').'?dialog=1'" show-label>
                            {{ __('Neue Veranstaltung') }}
                        </x-icon-btn>
                    @endcan
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="card bg-base-100 shadow">
            <div class="card-body p-0">
                <div class="grid grid-cols-7 bg-base-200 text-center text-xs font-semibold uppercase tracking-wide">
                    @foreach ($weekDays as $w)
                        <div class="px-2 py-2">{{ $w }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7">
                    @foreach ($days as $day)
                        @php
                            $isOther   = $day->month !== $monthStart->month;
                            $isToday   = $day->isToday();
                            $dayEvents = $eventsByDay->get($day->format('Y-m-d'), collect());
                        @endphp
                        <div class="min-h-28 border-b border-r border-base-300 p-1 align-top {{ $isOther ? 'bg-base-200/40 text-base-content/50' : '' }} {{ $isToday ? 'ring-1 ring-inset ring-primary' : '' }}">
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-semibold">{{ $day->day }}</span>
                                @if ($dayEvents->isNotEmpty())
                                    <span class="badge badge-xs badge-ghost">{{ $dayEvents->count() }}</span>
                                @endif
                            </div>
                            <div class="space-y-1">
                                @foreach ($dayEvents->take(4) as $event)
                                    <a href="{{ route('events.show', $event) }}"
                                       class="block truncate rounded px-1 py-0.5 text-xs text-white"
                                       style="background:{{ $event->category?->color ?? '#3b82f6' }}"
                                       title="{{ $event->title }} – {{ $event->started_at?->isoFormat('HH:mm') }}">
                                        <strong>{{ $event->started_at?->format('H:i') }}</strong>
                                        {{ $event->title }}
                                    </a>
                                @endforeach
                                @if ($dayEvents->count() > 4)
                                    <div class="text-xs opacity-70">+{{ $dayEvents->count() - 4 }} {{ __('weitere') }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </x-page-shell>
@endsection
