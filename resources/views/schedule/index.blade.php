@extends('layouts.app')
@section('title', __('Schichtplan') . ' — WorkDiary')
@section('nav-title', __('Schichtplan'))

@section('content')
@php
    /** @var string $view week|month */
    /** @var \Carbon\CarbonImmutable $anchor */
    /** @var \Carbon\CarbonImmutable $from */
    /** @var \Carbon\CarbonImmutable $to */
    /** @var string $prevDate */
    /** @var string $nextDate */
    /** @var string $todayDate */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ScheduledShift> $shifts */
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection> $shiftsByDate */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ShiftType> $shiftTypes */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \App\Services\HolidayService $holidays */
    /** @var int $userFilter */
    /** @var bool $isAdmin */

    $periodLabel = $view === 'month'
        ? $anchor->translatedFormat('F Y')
        : $from->translatedFormat('d.m.') . ' – ' . $to->translatedFormat('d.m.Y') . ' KW ' . $from->weekOfYear;
@endphp

<div class="mx-auto flex h-[calc(100dvh-5rem)] w-full max-w-screen-2xl flex-col px-4 xl:px-8 2xl:px-12">

    {{-- ── Toolbar ─────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-300 py-3">
        {{-- Period navigation --}}
        <div class="flex items-center gap-2">
            <div class="join">
                <a href="{{ route('schedule.index', array_filter(['view' => $view, 'date' => $prevDate, 'user' => $userFilter ?: null])) }}"
                   class="btn btn-sm btn-ghost join-item" title="{{ __('Zurück') }}">‹</a>
                <a href="{{ route('schedule.index', array_filter(['view' => $view, 'date' => $todayDate, 'user' => $userFilter ?: null])) }}"
                   class="btn btn-sm btn-ghost join-item">{{ __('Heute') }}</a>
                <a href="{{ route('schedule.index', array_filter(['view' => $view, 'date' => $nextDate, 'user' => $userFilter ?: null])) }}"
                   class="btn btn-sm btn-ghost join-item" title="{{ __('Weiter') }}">›</a>
            </div>
            <span class="font-['Space_Grotesk'] text-base font-semibold">{{ $periodLabel }}</span>
        </div>

        {{-- View toggle --}}
        <div class="join">
            <a href="{{ route('schedule.index', array_filter(['view' => 'week', 'date' => $anchor->toDateString(), 'user' => $userFilter ?: null])) }}"
               class="btn btn-sm join-item {{ $view === 'week' ? 'btn-primary' : 'btn-ghost' }}">{{ __('Woche') }}</a>
            <a href="{{ route('schedule.index', array_filter(['view' => 'month', 'date' => $anchor->toDateString(), 'user' => $userFilter ?: null])) }}"
               class="btn btn-sm join-item {{ $view === 'month' ? 'btn-primary' : 'btn-ghost' }}">{{ __('Monat') }}</a>
        </div>

        {{-- Filters + Actions --}}
        <form method="GET" action="{{ route('schedule.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="hidden" name="date" value="{{ $anchor->toDateString() }}">
            <select name="user" class="select select-sm select-bordered" onchange="this.form.submit()">
                <option value="">{{ __('Alle Mitarbeiter') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected($userFilter === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </form>

        @if ($isAdmin)
            <div class="flex gap-2">
                <button type="button" id="btn-open-type-manager" class="btn btn-sm btn-ghost">⚙ {{ __('Schichttypen') }}</button>
                <a href="{{ route('schedule.import') }}" class="btn btn-sm btn-ghost">↑ {{ __('Import') }}</a>
            </div>
        @endif
    </div>

    {{-- ── Flash messages ──────────────────────────────────────────────── --}}
    @if (session('success'))
        <div class="alert alert-success alert-sm my-2 py-2">{{ session('success') }}</div>
    @endif
    @if (session('import_errors'))
        <div class="alert alert-warning alert-sm my-2 py-2">
            <ul class="list-disc pl-4 text-xs">
                @foreach (session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Matrix ──────────────────────────────────────────────────────── --}}
    <div class="min-h-0 flex-1 overflow-auto">
        @if ($view === 'month')
            @include('schedule.partials._month_matrix')
        @else
            @include('schedule.partials._week_matrix')
        @endif
    </div>

</div>

{{-- ── Shift dialog ─────────────────────────────────────────────────────── --}}
@include('schedule.partials._shift_dialog')

{{-- ── Shift-type manager ──────────────────────────────────────────────── --}}
@if ($isAdmin)
    @include('schedule.partials._shift_type_manager')
@endif

<script>
window.__scheduleConfig = {
    isAdmin: {{ $isAdmin ? 'true' : 'false' }},
    csrf: @json(csrf_token()),
    routes: {
        shiftsStore:   @json(route('schedule.shifts.store')),
        shiftsUpdate:  @json(url('schedule/shifts')),
        shiftsDestroy: @json(url('schedule/shifts')),
        typesStore:    @json(route('schedule.types.store')),
        typesUpdate:   @json(url('schedule/types')),
        typesDestroy:  @json(url('schedule/types')),
    },
    shiftTypes: @json($shiftTypes->values()),
    users: @json($users->values()),
};
</script>
@vite('resources/js/schedule.js')
@endsection
