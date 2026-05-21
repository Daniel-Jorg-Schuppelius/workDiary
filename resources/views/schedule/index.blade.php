@extends('layouts.app')
@section('title', __('Schichtplan') . ' — WorkDiary')
@section('nav-title', __('Schichtplan'))
@section('wrapper-height-class', 'h-[calc(100dvh_-_var(--app-header-h))] overflow-clip')
@section('main-class', 'min-h-0 overflow-clip flex flex-col')

@section('content')
@php
    /** @var string $view week|month */
    /** @var \Carbon\CarbonImmutable $anchor */
    /** @var \Carbon\CarbonImmutable $from */
    /** @var \Carbon\CarbonImmutable $to */
    /** @var string $todayDate */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ScheduledShift> $shifts */
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection> $shiftsByDate */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ShiftType> $shiftTypes */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \App\Services\HolidayService $holidays */
    /** @var int $userFilter */
    /** @var bool $isAdmin */
@endphp

<x-page-shell overflow="clip">

    {{-- ── Filter & Toolbar ────────────────────────────────────────────── --}}
    <x-filter-bar :action="route('schedule.index')" class="bg-base-100!">
        {{-- User filter --}}
        <select name="user" class="select select-bordered select-sm w-full sm:w-auto sm:min-w-48" onchange="this.form.submit()">
            <option value="">{{ __('Alle Mitarbeiter') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected($userFilter === $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>

        @if ($userFilter)
            <x-icon-btn icon="restart_alt" size="sm" :href="route('schedule.index')" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
        @endif

        @if ($isAdmin)
            <x-slot:extra>
                <x-icon-btn icon="tune" size="sm" type="button" id="btn-open-type-manager" show-label>{{ __('Schichttypen') }}</x-icon-btn>
                <x-icon-btn icon="upload" size="sm" :href="route('schedule.import')" show-label>{{ __('Import') }}</x-icon-btn>
                <div class="dropdown dropdown-end">
                    <label tabindex="0" class="btn btn-sm btn-ghost gap-1">
                        <x-icon name="print" /><span>{{ __('Drucken') }}</span>
                    </label>
                    <ul tabindex="0" class="menu dropdown-content z-1 mt-2 w-72 rounded-box bg-base-100 p-2 shadow">
                        <li class="menu-title">{{ __('Übersichten') }}</li>
                        <li><a href="{{ route('print.on-call') }}" target="_blank">{{ __('Bereitschaft & Notdienst (A4 quer)') }}</a></li>
                        <li><a href="{{ route('print.vacations', ['year' => $anchor->year]) }}" target="_blank">{{ __('Urlaubsübersicht ') . $anchor->year . __(' (A4 hoch)') }}</a></li>
                    </ul>
                </div>
            </x-slot:extra>
        @endif
    </x-filter-bar>

    {{-- ── Flash messages ──────────────────────────────────────────────── --}}
    @if (session('import_errors'))
        <div class="alert alert-warning alert-sm my-2 py-2">
            <ul class="list-disc pl-4 text-xs">
                @foreach (session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Monats-Tabs (nur wenn globaler Zeitraum > 1 Monat) ──────────── --}}
    @if (count($months) > 1)
        <div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
            @foreach ($months as $m)
                @php
                    $tabParams = ['activeMonth' => $m['key']];
                    if ($userFilter) {
                        $tabParams['user'] = $userFilter;
                    }
                    if (in_array($view, ['week', 'month'], true)) {
                        $tabParams['view'] = $view;
                    }
                @endphp
                <a role="tab"
                   href="{{ route('schedule.index', $tabParams) }}"
                   class="tab whitespace-nowrap gap-1.5 {{ $m['key'] === $activeMonthKey ? 'tab-active' : '' }}">
                    <span class="font-semibold">{{ $m['shortLabel'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ── Matrix ──────────────────────────────────────────────────────── --}}
    <div class="min-h-0 flex-1 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
        @if ($view === 'month')
            @include('schedule.partials._month_matrix')
        @else
            @include('schedule.partials._week_matrix')
        @endif
    </div>

</x-page-shell>

{{-- ── Shift dialog ─────────────────────────────────────────────────────── --}}
@include('schedule.partials._shift_dialog')

{{-- ── Shift-type manager ──────────────────────────────────────────────── --}}
@if ($isAdmin)
    @include('schedule.partials._shift_type_manager')
@endif

<script>
window.__scheduleConfig = {
    isAdmin: {{ $isAdmin ? 'true' : 'false' }},
    currentUserId: {{ (int) auth()->id() }},
    csrf: @json(csrf_token()),
    routes: {
        shiftsStore:   @json(route('schedule.shifts.store')),
        shiftsUpdate:  @json(url('schedule/shifts')),
        shiftsDestroy: @json(url('schedule/shifts')),
        shiftsPublish: @json(url('schedule/shifts')),
        shiftsConfirm: @json(url('schedule/shifts')),
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
