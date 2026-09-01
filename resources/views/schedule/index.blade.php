{{--
  Created on   : Mon May 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Schichtplan') . ' — WorkDiary')
@section('nav-title', __('Schichtplan'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

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

<x-index-page overflow="clip" :subtitle="__('Personalplanung und Schichtzuweisung des Mandanten.')">
    @include('schedule._shift_tabs')

    {{-- ── Filter & Toolbar ────────────────────────────────────────────── --}}
    <x-filter-bar :action="route('schedule.index')" class="bg-base-100!">
        {{-- User filter --}}
        <select name="user" class="select select-bordered select-sm w-full sm:w-auto sm:min-w-48" data-autosubmit aria-label="{{ __('Mitarbeiter') }}">
            <option value="">{{ __('Alle Mitarbeiter') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected(\App\Support\Sqid::encode(\App\Models\User::class, $userFilter) === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </select>

        @if ($userFilter)
            <x-icon-btn icon="restart_alt" size="sm" :href="route('schedule.index')" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
        @endif

        @if ($isAdmin)
            <x-slot:extra>
                <x-icon-btn icon="tune" size="sm" type="button" id="btn-open-type-manager" show-label>{{ __('Schichttypen') }}</x-icon-btn>
                {{-- Import & Drucken öffnen echte Dialoge (s. _print_import_dialogs) statt
                     Vollseiten-Navigation bzw. CSS-Dropdown (das im overflow-clip-Container unsichtbar war). --}}
                <x-icon-btn icon="upload" size="sm" type="button" id="btn-open-import" show-label>{{ __('Import') }}</x-icon-btn>
                <x-icon-btn icon="print" size="sm" type="button" id="btn-open-print" show-label>{{ __('Drucken') }}</x-icon-btn>
            </x-slot:extra>
        @endif
    </x-filter-bar>

    {{-- Prerequisite-Referenzfall (Feature 067, MVP-181): ohne angelegte
         Schichttypen bleibt der Tagesklick nicht mehr stumm — geführter
         Setup-Hinweis; Admins öffnen den Typ-Manager direkt. --}}
    @if ($shiftTypes->isEmpty())
        <div role="alert" class="alert alert-warning alert-soft mt-2 text-sm" data-prerequisite="shift-types">
            <x-icon name="settings_alert" />
            <span>{{ __('prerequisites.shift_types.missing') }}</span>
            @if ($isAdmin)
                <x-button type="button" size="sm" tone="warning" id="btn-open-type-manager-hint"
                          data-open-dialog="shift-type-manager">
                    {{ __('prerequisites.shift_types.cta') }}
                </x-button>
            @else
                <span class="text-xs text-base-content/70">{{ __('prerequisites.contact_role', ['role' => __('Administration')]) }}</span>
            @endif
        </div>
    @endif

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
                    if (! empty($userFilterSqid)) {
                        $tabParams['user'] = $userFilterSqid;
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
    @if ($view === 'month')
        {{-- <x-month-calendar> bringt eigenen Rahmen + rounded-box + shadow mit,
             daher KEIN zusätzlicher Wrapper-Border (vermeidet Doppel-Rahmen). --}}
        @include('schedule.partials._month_matrix')
    @else
        <div class="min-h-0 flex-1 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
            @include('schedule.partials._week_matrix')
        </div>
    @endif

</x-index-page>

{{-- ── Shift dialog ─────────────────────────────────────────────────────── --}}
@include('schedule.partials._shift_dialog')

{{-- ── Shift-type manager + Druck/Import-Dialoge ───────────────────────── --}}
@if ($isAdmin)
    @include('schedule.partials._shift_type_manager')
    @include('schedule.partials._print_import_dialogs')
@endif

@php
    $scheduleShiftTypes = $shiftTypes->values()->map(fn ($type) => [
        'id' => $type->sqid,
        'name' => $type->name,
        'abbreviation' => $type->abbreviation,
        'color' => $type->color,
        'default_start_time' => $type->default_start_time,
        'default_end_time' => $type->default_end_time,
        'is_active' => (bool) $type->is_active,
    ]);

    $scheduleUsers = $users->values()->map(fn ($user) => [
        'id' => $user->sqid,
        'name' => $user->name,
    ]);
@endphp

<script @cspNonce>
window.__scheduleConfig = {
    isAdmin: {{ $isAdmin ? 'true' : 'false' }},
    currentUserId: @json(auth()->user()?->sqid),
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
        staffingSuggest: @json(route('schedule.suggest')),
    },
    canSuggest: {{ ($canSuggest ?? false) ? 'true' : 'false' }},
    shiftTypes: @json($scheduleShiftTypes),
    users: @json($scheduleUsers),
};
</script>
@vite('resources/js/schedule.js')
@endsection
